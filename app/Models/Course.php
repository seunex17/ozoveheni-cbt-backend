<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'code',
    ];

    public function singleExams(): HasMany
    {
        return $this->hasMany(SingleExam::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
