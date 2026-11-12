<?php

use App\Models\Department;
use App\Models\Exam;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function statusExam(string $start, string $end, string $status = 'pending'): Exam
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Test department']);

    return Exam::create([
        'uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'Scheduled exam',
        'set' => '2025/2026', 'level' => 'nd1', 'start_date' => $start, 'end_date' => $end, 'status' => $status,
    ]);
}

test('exam statuses match their full calendar periods', function (string $start, string $end, string $initial, string $expected) {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    $exam = statusExam($start, $end, $initial);
    $this->artisan('exams:update-statuses')->assertSuccessful();
    expect($exam->fresh()->status)->toBe($expected);
})->with([
    'future examination' => ['2026-11-13', '2026-11-20', 'completed', 'pending'],
    'starts today' => ['2026-11-12', '2026-11-20', 'pending', 'active'],
    'within period' => ['2026-11-10', '2026-11-15', 'pending', 'active'],
    'ends today' => ['2026-11-01', '2026-11-12', 'pending', 'active'],
    'single day period' => ['2026-11-12', '2026-11-12', 'pending', 'active'],
    'ended yesterday' => ['2026-11-01', '2026-11-11', 'pending', 'completed'],
    'old ongoing examination' => ['2026-10-01', '2026-10-30', 'active', 'completed'],
]);

test('status switches at Lagos midnight and keeps the entire final day ongoing', function () {
    $exam = statusExam('2026-11-12', '2026-11-12');
    $this->travelTo(new DateTimeImmutable('2026-11-11 22:59:59 UTC'));
    $this->artisan('exams:update-statuses')->assertSuccessful();
    expect($exam->fresh()->status)->toBe('pending');
    $this->travelTo(new DateTimeImmutable('2026-11-11 23:00:00 UTC'));
    $this->artisan('exams:update-statuses')->assertSuccessful();
    expect($exam->fresh()->status)->toBe('active');
    $this->travelTo(new DateTimeImmutable('2026-11-12 22:59:59 UTC'));
    $this->artisan('exams:update-statuses')->assertSuccessful();
    expect($exam->fresh()->status)->toBe('active');
    $this->travelTo(new DateTimeImmutable('2026-11-12 23:00:00 UTC'));
    $this->artisan('exams:update-statuses')->assertSuccessful();
    expect($exam->fresh()->status)->toBe('completed');
});

test('status synchronization corrects stale statuses and skips unchanged rows on repeat runs', function () {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    $future = statusExam('2026-11-13', '2026-11-20', 'active');
    $ongoing = statusExam('2026-11-10', '2026-11-15', 'completed');
    $past = statusExam('2026-11-01', '2026-11-11');
    $this->artisan('exams:update-statuses')->expectsOutput('Updated exams: 1 pending, 1 ongoing, 1 completed.')->assertSuccessful();
    $timestamps = [$future->fresh()->updated_at, $ongoing->fresh()->updated_at, $past->fresh()->updated_at];
    $this->travel(5)->minutes();
    $this->artisan('exams:update-statuses')->expectsOutput('Updated exams: 0 pending, 0 ongoing, 0 completed.')->assertSuccessful();
    foreach ([$future, $ongoing, $past] as $index => $exam) {
        expect($exam->fresh()->updated_at->equalTo($timestamps[$index]))->toBeTrue();
    }
});

test('status command succeeds with no examinations', function () {
    $this->artisan('exams:update-statuses')->expectsOutput('Updated exams: 0 pending, 0 ongoing, 0 completed.')->assertSuccessful();
});

test('exam status synchronization is registered every five minutes without overlap', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'exams:update-statuses'));
    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('*/5 * * * *');
    expect($event->withoutOverlapping)->toBeTrue();
});
