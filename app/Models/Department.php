<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'uuid',
        'name',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
