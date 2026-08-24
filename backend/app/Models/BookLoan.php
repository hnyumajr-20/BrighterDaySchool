<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['book_id', 'student_id', 'issued_at', 'due_date', 'returned_at'])]
class BookLoan extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_date' => 'date',
            'returned_at' => 'date',
        ];
    }
}
