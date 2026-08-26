<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'date', 'check_in_opens_at', 'check_in_closes_at',
    'check_out_opens_at', 'check_out_closes_at', 'opened_by', 'absentees_marked_at',
])]
class StaffAttendanceWindow extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_opens_at' => 'datetime',
            'check_in_closes_at' => 'datetime',
            'check_out_opens_at' => 'datetime',
            'check_out_closes_at' => 'datetime',
            'absentees_marked_at' => 'datetime',
        ];
    }
}
