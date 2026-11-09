<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property array<int, array{id: int, option_ids: array<int, int>}>|null $question_order
 */
class Attempt extends Model
{
    protected $hidden = ['access_token_hash', 'login_key_hash', 'submission_payload_hash', 'question_order'];

    protected $fillable = [
        'student_id',
        'single_exam_id',
        'started_at',
        'submitted_at',
        'status',
        'access_token_hash',
        'login_key_hash',
        'expires_at',
        'question_order',
        'submission_id',
        'submission_payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'question_order' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SingleExam, $this> */
    public function singleExam(): BelongsTo
    {
        return $this->belongsTo(SingleExam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
