<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'arm', 'fee_amount_cents', 'academic_year_id'])]
class SchoolClass extends Model
{
    protected $table = 'classes';

    public $timestamps = false;

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function classFeeInstallments(): HasMany
    {
        return $this->hasMany(ClassFeeInstallment::class, 'class_id');
    }
}
