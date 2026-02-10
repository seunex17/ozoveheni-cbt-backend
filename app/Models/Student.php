<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'department_id',
        'uuid',
        'reg_no',
        'set',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'dob',
        'photo',
        'level',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
