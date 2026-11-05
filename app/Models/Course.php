<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'unit',
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
