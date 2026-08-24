<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['class_subject_id', 'timetable_slot_id'])]
class Schedule extends Model
{
    public $timestamps = false;
}
