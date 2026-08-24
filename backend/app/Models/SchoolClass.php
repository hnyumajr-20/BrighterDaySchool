<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'arm', 'fee_amount_cents', 'academic_year_id'])]
class SchoolClass extends Model
{
    protected $table = 'classes';

    public $timestamps = false;
}
