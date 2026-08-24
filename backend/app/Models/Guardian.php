<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['full_name', 'phone', 'email', 'address'])]
class Guardian extends Model
{
    protected $table = 'parents';
}
