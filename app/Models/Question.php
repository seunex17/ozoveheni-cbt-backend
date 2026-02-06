<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'single_exam_id',
        'question_text',
        'marks',
    ];

    public function singleExam(): BelongsTo
    {
        return $this->belongsTo(SingleExam::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
