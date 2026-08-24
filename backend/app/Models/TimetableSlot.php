<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'day_of_week', 'start_time', 'end_time'])]
class TimetableSlot extends Model
{
    public $timestamps = false;
}
