<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'author', 'isbn', 'copies_total', 'copies_available'])]
class Book extends Model
{
    public $timestamps = false;
}
