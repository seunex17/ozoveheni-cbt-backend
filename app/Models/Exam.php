<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'uuid',
        'department_id',
        'title',
        'subtitle',
        'set',
        'start_date',
        'end_date',
        'status',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function singleExams(): HasMany
    {
        return $this->hasMany(SingleExam::class);
    }
}
