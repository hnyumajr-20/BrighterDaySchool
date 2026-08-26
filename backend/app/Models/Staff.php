<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id', 'staff_no', 'full_name', 'dob', 'gender', 'email', 'image_path',
    'cv_path', 'rfid_uid', 'contact', 'address', 'staff_role', 'salary_cents', 'status',
])]
class Staff extends Model
{
    protected $table = 'staff';

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }
}
