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
    /** @return Builder<ExamHall> */
    public function participants(): Builder
    {
        return ExamHall::query()->select(['id', 'student_id', 'attempt_id'])->whereHas('attempt');
    }

    public function count(): int
    {
        return $this->participants()->whereHas('attempt', function (Builder $query): void {
            $query->where('status', 'in_progress')->whereNull('submitted_at');
        })->distinct()->count('attempt_id');
    }

    public function snapshot(): array
    {
        $started = now()->toISOString();
        $participants = $this->participants()->with(['student:id,first_name,middle_name,last_name,reg_no,photo', 'attempt:id,status,submitted_at'])
            ->orderBy('id')->get()->unique('attempt_id')->map(fn (ExamHall $hall): array => $this->profile($hall))->values();

        return [
            'participants' => $participants,
            'count' => $participants->where('status', 'ongoing')->count(),
            'submitted_count' => $participants->where('status', 'submitted')->count(),
            'snapshot_started_at' => $started,
            'server_time' => now()->toISOString(),
        ];
    }

    public function profile(ExamHall $hall): array
    {
        $student = $hall->student;
        $attempt = $hall->attempt;

        return [
            'attempt_id' => $hall->attempt_id,
            'student' => [
                'name' => trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name]))),
                'reg_no' => $student->reg_no,
                'photo_url' => $student->photo ? Storage::disk('public')->url($student->photo) : null,
            ],
            'status' => $attempt->status === 'completed' || $attempt->submitted_at !== null ? 'submitted' : 'ongoing',
        ];
    }

    public function publish(string $operation, ?int $attemptId = null): void
    {
        DB::afterCommit(function () use ($operation, $attemptId): void {
            $participant = null;
            if ($operation === 'upsert') {
                $hall = $this->participants()->where('attempt_id', $attemptId)->with(['student:id,first_name,middle_name,last_name,reg_no,photo', 'attempt:id,status,submitted_at'])->first();
                if (! $hall) {
                    return;
                }
                $participant = $this->profile($hall);
            }
            try {
                event(new ExamHallUpdated($operation, $attemptId, $participant, now()->toISOString()));
            } catch (Throwable $exception) {
                report($exception);
            }
            try {
                event(new ExamHallEvent($this->count()));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
