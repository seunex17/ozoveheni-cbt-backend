<?php

namespace App\Services;

use App\Events\ExamHallEvent;
use App\Events\ExamHallUpdated;
use App\Models\ExamHall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExamHallMonitor
{
    public const HEARTBEAT_SECONDS = 20;

    public const STALE_AFTER_SECONDS = 60;

    /** @return Builder<ExamHall> */
    public function participants(): Builder
    {
        return ExamHall::query()->whereHas('attempt', function (Builder $query): void {
            $query->where('status', 'in_progress')->whereNull('submitted_at');
        });
    }

    public function count(): int
    {
        return $this->participants()->distinct()->count('attempt_id');
    }

    public function snapshot(): array
    {
        $participants = $this->participants()
            ->with(['student.department', 'attempt.singleExam.course', 'attempt.singleExam.exam'])
            ->orderBy('id')
            ->get()
            ->unique('attempt_id')
            ->map(function (ExamHall $hall): array {
                $student = $hall->student;
                $attempt = $hall->attempt;
                $paper = $attempt->singleExam;

                return [
                    'attempt_id' => $hall->attempt_id,
                    'student' => [
                        'name' => trim(implode(' ', array_filter([
                            $student->first_name, $student->middle_name, $student->last_name,
                        ]))),
                        'reg_no' => $student->reg_no,
                        'photo_url' => $student->photo ? Storage::disk('public')->url($student->photo) : null,
                        'department' => $student->department->name,
                    ],
                    'exam' => [
                        'course' => $paper->course->name,
                        'code' => $paper->course->code,
                        'title' => $paper->exam->title,
                    ],
                    'started_at' => $attempt->started_at?->toISOString(),
                    ...$this->progress($hall),
                ];
            })->values();

        return [
            'participants' => $participants,
            'count' => $participants->count(),
            'server_time' => now()->toISOString(),
            'stale_after_seconds' => self::STALE_AFTER_SECONDS,
        ];
    }

    public function progress(ExamHall $hall): array
    {
        return [
            'last_seen_at' => $hall->last_seen_at?->toISOString(),
            'current_question_id' => $hall->current_question_id,
            'current_question_number' => $hall->current_question_number,
            'total_questions' => $hall->total_questions,
            'last_sequence' => $hall->last_sequence,
        ];
    }

    public function publish(string $operation, ?int $attemptId = null, array $progress = []): void
    {
        DB::afterCommit(function () use ($operation, $attemptId, $progress): void {
            try {
                event(new ExamHallUpdated($operation, $attemptId, $progress));
            } catch (Throwable $exception) {
                report($exception);
            }

            if ($operation !== 'progress') {
                try {
                    event(new ExamHallEvent($this->count()));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }
}
