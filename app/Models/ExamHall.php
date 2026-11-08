<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class ExamHall extends Model
{
    protected $fillable = [
        'student_id',
        'attempt_id',
        'monitor_token_hash',
        'last_seen_at',
        'current_question_id',
        'current_question_number',
        'total_questions',
        'last_sequence',
    ];

    protected $hidden = ['monitor_token_hash'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'current_question_id' => 'integer',
            'current_question_number' => 'integer',
            'total_questions' => 'integer',
            'last_sequence' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Attempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
