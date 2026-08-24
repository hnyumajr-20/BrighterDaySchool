<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['class_id', 'subject_id', 'teacher_id'])]
class ClassSubject extends Model
{
    public $timestamps = false;
}
