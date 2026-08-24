<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'date', 'status', 'method', 'recorded_by'])]
class StudentAttendance extends Model
{
    protected $table = 'student_attendance';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
