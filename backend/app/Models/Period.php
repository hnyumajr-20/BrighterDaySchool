<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['semester_id', 'name', 'sequence', 'is_exam_period', 'start_date', 'end_date', 'status'])]
class Period extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_exam_period' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
