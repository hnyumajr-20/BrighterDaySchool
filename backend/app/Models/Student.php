<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'admission_no', 'full_name', 'dob', 'gender', 'email', 'image_path',
    'is_transfer_student', 'transcript_path', 'contact', 'address', 'parent_id', 'class_id', 'status',
])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'is_transfer_student' => 'boolean',
        ];
    }
}
