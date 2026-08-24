<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'staff_no', 'full_name', 'dob', 'gender', 'email', 'image_path',
    'cv_path', 'contact', 'address', 'staff_role', 'salary_cents', 'status',
])]
class Staff extends Model
{
    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }
}
