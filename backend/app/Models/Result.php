<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'subject_id', 'period_id', 'score', 'recorded_by'])]
class Result extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }
}
