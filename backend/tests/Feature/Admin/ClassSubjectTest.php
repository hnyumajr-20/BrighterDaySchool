<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassSubjectTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    private function makeClass(): SchoolClass
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        return SchoolClass::create([
            'name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => 4500000, 'academic_year_id' => $year->id,
        ]);
    }

    private function makeTeacher(): Staff
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")->post('/api/v1/staff', [
            'full_name' => 'Teacher One',
            'dob' => '1990-01-01',
            'email' => 'teacher.one.'.uniqid().'@brighterday.test',
            'contact' => '0770000000',
            'staff_role' => 'teacher',
            'salary_cents' => 30000000,
            'photo' => UploadedFile::fake()->image('photo.jpg')->size(200),
            'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
        ])->json();

        return Staff::find($created['id']);
    }

    public function test_admin_can_assign_a_subject_and_teacher_to_a_class(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $teacher = $this->makeTeacher();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('subject.name', 'Mathematics')
            ->assertJsonPath('teacher.full_name', 'Teacher One');

        $list = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/classes/{$class->id}/subjects");
        $list->assertOk();
        $this->assertCount(1, $list->json());
    }

    public function test_cannot_assign_the_same_subject_to_a_class_twice(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $payload = ['subject_id' => $subject->id];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", $payload)
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", $payload)
            ->assertUnprocessable();
    }

    public function test_admin_can_change_the_teacher_assigned_to_a_class_subject(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $teacherA = $this->makeTeacher();
        $teacherB = $this->makeTeacher();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", [
                'subject_id' => $subject->id,
                'teacher_id' => $teacherA->id,
            ])->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/classes/{$class->id}/subjects/{$created['id']}", ['teacher_id' => $teacherB->id]);

        $response->assertOk()->assertJsonPath('teacher.id', $teacherB->id);
    }

    public function test_admin_can_remove_a_subject_from_a_class(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", ['subject_id' => $subject->id])
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/classes/{$class->id}/subjects/{$created['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('class_subjects', ['id' => $created['id']]);
    }

    public function test_deleting_a_class_removes_its_subject_assignments(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", ['subject_id' => $subject->id])
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/classes/{$class->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('class_subjects', ['id' => $created['id']]);
    }

    public function test_deleting_a_subject_removes_its_class_assignments(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", ['subject_id' => $subject->id])
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('class_subjects', ['id' => $created['id']]);
    }

    public function test_deleting_a_teacher_unassigns_them_rather_than_failing(): void
    {
        $token = $this->adminToken();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $teacher = $this->makeTeacher();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/staff/{$teacher->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('class_subjects', ['id' => $created['id'], 'teacher_id' => null]);
    }

    public function test_non_admin_cannot_assign_subjects_to_a_class(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/subjects", ['subject_id' => $subject->id]);

        $response->assertForbidden();
    }
}
