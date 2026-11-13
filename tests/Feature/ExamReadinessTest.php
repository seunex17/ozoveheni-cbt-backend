<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function readinessFixture(): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
    $course = Course::create(['uuid' => (string) str()->uuid(), 'code' => 'ANA101', 'name' => 'Anatomy']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'First semester', 'set' => '2025/2026', 'level' => 'nd', 'start_date' => now()->addDay()->startOfDay(), 'end_date' => now()->addDays(2)->startOfDay()]);
    $paper = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'start' => now()->addDay()->startOfDay()->addHours(8), 'end' => now()->addDay()->startOfDay()->addHours(12), 'duration' => 40]);
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'STD/25/003', 'set' => $exam->set, 'level' => 'nd', 'first_name' => 'Ada', 'middle_name' => '', 'last_name' => 'James', 'gender' => 'Female', 'photo' => 'students/ada.jpg']);
    $question = Question::create(['single_exam_id' => $paper->id, 'question_text' => '<p>Question?</p>', 'marks' => 2]);
    $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    $wrong = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false]);

    return compact('department', 'course', 'exam', 'paper', 'student', 'question', 'correct', 'wrong');
}

function readinessPage($test, Exam $exam): array
{
    return $test->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))
        ->get(route('exam.readiness', $exam->uuid))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/Exams/Readiness'))
        ->viewData('page')['props'];
}

test('an exam with valid papers questions and student records is ready and checking does not create attempts', function () {
    $fixture = readinessFixture();
    for ($index = 1; $index < 60; $index++) {
        $question = Question::create(['single_exam_id' => $fixture['paper']->id, 'question_text' => 'Question '.$index, 'marks' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Yes', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'No', 'is_correct' => false]);
    }
    $props = readinessPage($this, $fixture['exam']);
    expect($props['summary'])->toMatchArray(['status' => 'ready', 'blockers' => 0, 'warnings' => 0, 'students' => 1, 'papers' => 1]);
    expect(collect($props['checks'])->every(fn (array $check) => $check['status'] === 'passed'))->toBeTrue();
    $this->assertDatabaseCount('questions', 60);
    $this->assertDatabaseCount('attempts', 0);
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseCount('exam_halls', 0);
});

test('empty papers and an exam without any papers are flagged with the correct editor links', function () {
    $fixture = readinessFixture();
    $fixture['question']->delete();
    $props = readinessPage($this, $fixture['exam']);
    $check = collect($props['checks'])->firstWhere('id', 'empty_papers');
    expect($check['status'])->toBe('blocker');
    expect($check['items'][0]['href'])->toBe(route('exam.addQuestion', $fixture['paper']->uuid));
    $fixture['paper']->delete();
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'papers')['status'])->toBe('blocker');
});

test('question defects are identified without sending answer keys to the readiness page', function (string $problem) {
    $fixture = readinessFixture();
    match ($problem) {
        'no correct answer' => $fixture['correct']->update(['is_correct' => false]),
        'too few options' => $fixture['wrong']->delete(),
        'empty text' => $fixture['question']->update(['question_text' => '<p>&nbsp; </p>']),
        'invalid marks' => $fixture['question']->update(['marks' => 0]),
    };
    $props = readinessPage($this, $fixture['exam']);
    $check = collect($props['checks'])->firstWhere('id', 'question_quality');
    expect($check['status'])->toBe('blocker');
    expect($check['count'])->toBe(1);
    expect($check['items'][0]['href'])->toBe(route('exam.viewQuestion', $fixture['question']->id));
    expect($props)->not->toHaveKey('questions');
})->with(['no correct answer', 'too few options', 'empty text', 'invalid marks']);

test('smaller banks and multiple correct answers are warnings rather than blocking valid single select exams', function () {
    $fixture = readinessFixture();
    $fixture['wrong']->update(['is_correct' => true]);
    $props = readinessPage($this, $fixture['exam']);
    $checks = collect($props['checks'])->keyBy('id');
    expect($checks['question_count']['status'])->toBe('warning');
    expect($checks['multiple_answers']['status'])->toBe('warning');
    expect($checks['question_quality']['status'])->toBe('passed');
    expect($props['summary']['status'])->toBe('review');
    expect($props['summary']['blockers'])->toBe(0);
});

test('invalid admission schedules are blocked while the personal timer can extend beyond closing', function (string $condition) {
    $fixture = readinessFixture();
    match ($condition) {
        'reversed' => $fixture['paper']->update(['end' => $fixture['paper']->start]),
        'outside exam dates' => $fixture['paper']->update(['end' => $fixture['exam']->end_date->copy()->addDay()]),
        'zero duration' => $fixture['paper']->update(['duration' => 0]),
        'full personal duration' => $fixture['paper']->update(['duration' => 400]),
    };
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'paper_schedule')['status'])->toBe($condition === 'full personal duration' ? 'passed' : 'blocker');
})->with(['reversed', 'outside exam dates', 'zero duration', 'full personal duration']);

test('overlapping course admissions are detected and adjacent windows are allowed', function () {
    $fixture = readinessFixture();
    $other = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $fixture['exam']->id, 'course_id' => $fixture['course']->id, 'start' => $fixture['paper']->end->copy()->subMinute(), 'end' => $fixture['paper']->end->copy()->addHour(), 'duration' => 40]);
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'paper_overlaps')['count'])->toBe(1);
    $other->update(['start' => $fixture['paper']->end]);
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'paper_overlaps')['status'])->toBe('passed');
});

test('overlapping exam groups only conflict for the same department set and level', function (string $difference) {
    $fixture = readinessFixture();
    $attributes = $fixture['exam']->getAttributes();
    unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
    $attributes['uuid'] = (string) str()->uuid();
    $attributes['title'] = 'Other semester';
    $attributes = array_replace($attributes, match ($difference) {
        'same group' => [],
        'level' => ['level' => 'hnd'],
        'set' => ['set' => '2024/2025'],
        'department' => ['department_id' => Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Other'])->id],
    });
    Exam::create($attributes);
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'exam_overlaps')['status'])->toBe($difference === 'same group' ? 'blocker' : 'passed');
})->with(['same group', 'level', 'set', 'department']);

test('student warnings use the selected cohort and database levels', function () {
    $fixture = readinessFixture();
    $fixture['student']->update(['photo' => null]);
    $other = Student::forceCreate([...$fixture['student']->getAttributes(), 'id' => 99, 'uuid' => (string) str()->uuid(), 'reg_no' => 'STD/25/004', 'level' => 'unknown']);
    $props = readinessPage($this, $fixture['exam']);
    $checks = collect($props['checks'])->keyBy('id');
    expect($props['summary']['students'])->toBe(1);
    expect($checks['student_photos']['count'])->toBe(1);
    expect($checks['student_levels']['count'])->toBe(1);
    expect($checks['student_levels']['items'][0]['href'])->toBe(route('student.edit', $other->uuid));
    Level::create(['code' => 'unknown', 'name' => 'Additional diploma']);
    $props = readinessPage($this, $fixture['exam']);
    expect(collect($props['checks'])->firstWhere('id', 'student_levels')['count'])->toBe(0);
    $fixture['exam']->update(['level' => 'unknown']);
    $props = readinessPage($this, $fixture['exam']);
    expect($props['exam']['level_name'])->toBe('Additional diploma');
    expect(collect($props['checks'])->firstWhere('id', 'exam_level')['status'])->toBe('passed');
});

test('missing students invalid levels reversed dates and closed admission are surfaced', function () {
    $fixture = readinessFixture();
    $fixture['student']->delete();
    $fixture['exam']->update(['level' => 'missing', 'end_date' => $fixture['exam']->start_date->copy()->subDay()]);
    $fixture['paper']->update(['end' => now()->subMinute()]);
    $props = readinessPage($this, $fixture['exam']);
    $checks = collect($props['checks'])->keyBy('id');
    foreach (['exam_dates', 'exam_level', 'eligible_students'] as $id) {
        expect($checks[$id]['status'])->toBe('blocker');
    }
    expect($checks['admission_closed']['status'])->toBe('warning');
});

test('readiness keeps large student previews bounded while retaining exact issue totals', function () {
    $fixture = readinessFixture();
    for ($index = 0; $index < 22; $index++) {
        Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $fixture['department']->id, 'reg_no' => 'MISSING/'.$index, 'set' => $fixture['exam']->set, 'level' => 'nd', 'first_name' => 'Student', 'middle_name' => '', 'last_name' => 'Missing', 'gender' => 'Female']);
    }
    $props = readinessPage($this, $fixture['exam']);
    $check = collect($props['checks'])->firstWhere('id', 'student_photos');
    expect($check['count'])->toBe(22);
    expect($check['items'])->toHaveCount(20);
});

test('only active administrators can inspect exam readiness and unknown exams do not fall back', function () {
    $fixture = readinessFixture();
    $url = route('exam.readiness', $fixture['exam']->uuid);
    $this->get($url)->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['is_admin' => false, 'status' => 'active']))->get($url)->assertRedirect('/dashboard');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'inactive']))->get($url)->assertRedirect('/');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))->get('/dashboard/exam/unknown/readiness')->assertNotFound();
});
