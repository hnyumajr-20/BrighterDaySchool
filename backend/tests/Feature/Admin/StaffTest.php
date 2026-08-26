<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendLoggedEmailJob;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function validStaffPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Grace Kollie',
            'dob' => '1990-05-14',
            'email' => 'grace.kollie@brighterday.test',
            'contact' => '0770000000',
            'staff_role' => 'teacher',
            'salary_cents' => 50000000,
            'photo' => UploadedFile::fake()->image('photo.jpg', 300, 300)->size(500),
            'cv' => UploadedFile::fake()->create('cv.pdf', 500, 'application/pdf'),
        ], $overrides);
    }

    public function test_admin_can_create_staff_with_photo_and_cv_and_credential_email_is_queued(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->post('/api/v1/staff', $this->validStaffPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('staff', [
            'full_name' => 'Grace Kollie',
            'staff_role' => 'teacher',
            'dob' => '1990-05-14',
            'contact' => '0770000000',
        ]);

        $staff = Staff::where('full_name', 'Grace Kollie')->firstOrFail();
        $this->assertNotNull($staff->user_id);
        $this->assertNotNull($staff->image_path);
        $this->assertNotNull($staff->cv_path);
        $this->assertNotNull($response->json('photo_url'));

        Storage::disk('public')->assertExists($staff->image_path);
        Storage::disk('local')->assertExists($staff->cv_path);

        $user = User::find($staff->user_id);
        $this->assertEquals('teacher', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertStringStartsWith('BDS-', $user->username);

        $this->assertDatabaseHas('email_log', [
            'user_id' => $user->id,
            'type' => 'staff_credentials',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendLoggedEmailJob::class);
    }

    public function test_staff_creation_requires_photo_and_cv_and_dob(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->post('/api/v1/staff', $this->validStaffPayload(['photo' => null, 'cv' => null, 'dob' => null]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo', 'cv', 'dob']);
    }

    public function test_staff_creation_rejects_non_pdf_cv(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->post('/api/v1/staff', $this->validStaffPayload([
                'cv' => UploadedFile::fake()->create('cv.docx', 200, 'application/msword'),
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cv']);
    }

    public function test_staff_creation_rejects_oversized_photo(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->post('/api/v1/staff', $this->validStaffPayload([
                'photo' => UploadedFile::fake()->image('photo.jpg')->size(3000),
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo']);
    }

    public function test_staff_creation_rejects_duplicate_email(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        User::factory()->create(['email' => 'taken@brighterday.test']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'taken@brighterday.test']));

        $response->assertUnprocessable();
    }

    public function test_non_admin_cannot_create_staff(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'blocked@brighterday.test']));

        $response->assertForbidden();
    }

    public function test_admin_can_list_and_update_staff(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'update.me@brighterday.test']))
            ->json();

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/staff');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json()));

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/staff/{$created['id']}", ['status' => 'inactive']);

        $update->assertOk()->assertJsonPath('status', 'inactive');
    }

    public function test_admin_can_edit_contact_address_role_and_salary(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'edit.everything@brighterday.test']))
            ->json();

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/staff/{$created['id']}", [
                'contact' => '0991234567',
                'address' => '45 New Address, Monrovia',
                'staff_role' => 'librarian',
                'salary_cents' => 60000000,
            ]);

        $update->assertOk()
            ->assertJsonPath('contact', '0991234567')
            ->assertJsonPath('address', '45 New Address, Monrovia')
            ->assertJsonPath('staff_role', 'librarian')
            ->assertJsonPath('salary_cents', 60000000);

        // The linked login's role stays in lockstep, since that's what RBAC checks.
        $staff = Staff::find($created['id']);
        $this->assertDatabaseHas('users', ['id' => $staff->user_id, 'role' => 'librarian']);
    }

    public function test_admin_can_assign_an_rfid_card_at_creation(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload([
                'email' => 'rfid.owner@brighterday.test',
                'rfid_uid' => '04A3B2C1',
            ]));

        $response->assertCreated()->assertJsonPath('rfid_uid', '04A3B2C1');
        $this->assertDatabaseHas('staff', ['email' => 'rfid.owner@brighterday.test', 'rfid_uid' => '04A3B2C1']);
    }

    public function test_two_staff_cannot_share_the_same_rfid_card(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload([
                'email' => 'first.card@brighterday.test',
                'rfid_uid' => 'DUPLICATE-UID',
            ]))->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload([
                'email' => 'second.card@brighterday.test',
                'rfid_uid' => 'DUPLICATE-UID',
            ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['rfid_uid']);
    }

    public function test_admin_can_assign_an_rfid_card_via_edit(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'card.later@brighterday.test']))
            ->json();

        $this->assertNull($created['rfid_uid']);

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/staff/{$created['id']}", ['rfid_uid' => 'ISSUED-LATER-01']);

        $update->assertOk()->assertJsonPath('rfid_uid', 'ISSUED-LATER-01');
    }

    public function test_deactivating_staff_blocks_their_login_and_revokes_existing_tokens(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $adminToken = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'deactivate.me@brighterday.test']))
            ->json();

        $staff = Staff::find($created['id']);
        $staffUser = User::find($staff->user_id);
        $staffUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/v1/staff/{$staff->id}", ['status' => 'inactive'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffUser->id, 'status' => 'inactive']);
        // Only the admin's own token remains — the staff member's was revoked.
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $staffUser->id]);

        // They can't log in fresh either — their user row is inactive.
        $this->postJson('/api/v1/auth/login', [
            'username_or_email' => $staffUser->username,
            'password' => 'irrelevant-because-inactive-check-runs-first',
        ])->assertUnprocessable();
    }

    public function test_deleting_staff_also_deactivates_their_login(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $adminToken = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'delete.me@brighterday.test']))
            ->json();

        $userId = Staff::find($created['id'])->user_id;

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->deleteJson("/api/v1/staff/{$created['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('staff', ['id' => $created['id']]);
        $this->assertDatabaseHas('users', ['id' => $userId, 'status' => 'inactive']);
    }

    public function test_admin_can_download_staff_cv(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff', $this->validStaffPayload(['email' => 'cv.owner@brighterday.test']))
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/staff/{$created['id']}/cv");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
