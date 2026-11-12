<?php

namespace App\Console\Commands;

use App\Models\Exam;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('exams:update-statuses')]
#[Description('Synchronize examination statuses with their start and end dates')]
class UpdateExamStatuses extends Command
{
    public function handle(): int
    {
        $day = now(config('app.timezone'))->startOfDay();
        $today = $day->toDateString();
        $tomorrow = $day->copy()->addDay()->toDateString();

        $counts = DB::transaction(function () use ($today, $tomorrow): array {
            $pending = Exam::query()->where('start_date', '>=', $tomorrow)
                ->where('end_date', '>=', $today)->where('status', '!=', 'pending')
                ->update(['status' => 'pending']);
            $active = Exam::query()->where('start_date', '<', $tomorrow)
                ->where('end_date', '>=', $today)->where('status', '!=', 'active')
                ->update(['status' => 'active']);
            $completed = Exam::query()->where('end_date', '<', $today)
                ->where('status', '!=', 'completed')->update(['status' => 'completed']);

            return compact('pending', 'active', 'completed');
        });

        $this->info("Updated exams: {$counts['pending']} pending, {$counts['active']} ongoing, {$counts['completed']} completed.");

        return self::SUCCESS;
    }
}
