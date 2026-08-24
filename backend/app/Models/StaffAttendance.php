<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['staff_id', 'date', 'status', 'method', 'recorded_by'])]
class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
