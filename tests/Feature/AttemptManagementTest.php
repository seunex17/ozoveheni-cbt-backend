<?php

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\Question;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use App\Services\ExamHallMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function attemptManagementFixture(): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Health']);
    $course = Course::create(['uuid' => (string) str()->uuid(), 'name' => 'Accounting', 'code' => 'ACC101']);
    $exams = [];
    $papers = [];
    foreach (['First semester', 'Second semester'] as $title) {
        $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => $title, 'set' => '2025/2026', 'level' => 'nd', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']);
        $exams[] = $exam;
        $papers[] = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'duration' => 40, 'start' => '2026-01-01 08:00:00', 'end' => '2026-01-01 12:00:00']);
    }
    $students = [];
    foreach (['Ada', 'Grace'] as $index => $firstName) {
        $students[] = Student::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'STD/25/00'.($index + 1), 'first_name' => $firstName, 'middle_name' => '', 'last_name' => 'James', 'gender' => 'female', 'set' => '2025/2026', 'level' => 'nd']);
    }
    $attempts = [];
    foreach ([[$students[0], $papers[0]], [$students[1], $papers[0]], [$students[0], $papers[1]]] as [$student, $paper]) {
        $attempts[] = Attempt::create(['student_id' => $student->id, 'single_exam_id' => $paper->id, 'status' => 'completed', 'submitted_at' => now()]);
    }
    $question = Question::create(['single_exam_id' => $papers[0]->id, 'question_text' => 'Question', 'marks' => 1]);
    $answer = Answer::create(['attempt_id' => $attempts[0]->id, 'question_id' => $question->id, 'is_correct' => false]);
    $hall = ExamHall::create(['student_id' => $students[0]->id, 'attempt_id' => $attempts[0]->id]);
    $report = Report::create(['exam_id' => $exams[0]->id, 'course_id' => $course->id, 'student_id' => $students[0]->id, 'first_ca' => 10, 'second_ca' => 12, 'exam' => 40, 'total_ca' => 22]);

    return compact('exams', 'papers', 'students', 'attempts', 'answer', 'hall', 'report');
}

test('attempt directory starts empty until an exam is selected and searches within that exam', function () {
    $data = attemptManagementFixture();
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']));
    $this->get(route('attempts'))->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/Attempts/Index')->has('students.data', 0)->where('totalAttempts', 0));
    $this->get(route('attempts', ['exam' => $data['exams'][0]->uuid, 'search' => 'ada james']))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('students.data', 1)->where('students.data.0.reg_no', 'STD/25/001')->where('students.data.0.attempts_count', 1)->where('students.data.0.submitted_count', 1)->where('totalAttempts', 2));
    $this->get(route('attempts', ['exam' => $data['exams'][1]->uuid, 'search' => 'STD/25/002']))->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 0)->where('totalAttempts', 1));
    $this->get(route('attempts', ['exam' => $data['exams'][0]->uuid, 'search' => '%']))->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 0));
});

test('clearing a student removes all their course attempts for only the selected exam and keeps results', function () {
    $data = attemptManagementFixture();
    $paper = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $data['exams'][0]->id, 'course_id' => $data['papers'][0]->course_id, 'duration' => 40, 'start' => now(), 'end' => now()->addHour()]);
    $extra = Attempt::create(['student_id' => $data['students'][0]->id, 'single_exam_id' => $paper->id]);
    $this->mock(ExamHallMonitor::class)->shouldReceive('publish')->once()->with('reset');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))
        ->post(route('attempts.clearStudent', ['exam' => $data['exams'][0]->uuid, 'student' => $data['students'][0]->uuid]), ['confirmed' => true])
        ->assertRedirectToRoute('attempts', ['exam' => $data['exams'][0]->uuid, 'search' => 'STD/25/001'])->assertSessionHasNoErrors();
    $this->assertModelMissing($data['attempts'][0]);
    $this->assertModelMissing($extra);
    $this->assertModelMissing($data['answer']);
    $this->assertModelMissing($data['hall']);
    $this->assertModelExists($data['attempts'][1]);
    $this->assertModelExists($data['attempts'][2]);
    expect($data['report']->fresh()->only(['exam', 'first_ca', 'second_ca']))->toBe(['exam' => 40, 'first_ca' => 10, 'second_ca' => 12]);
});

test('clearing an exam removes every students attempts regardless of search but preserves other exams', function () {
    $data = attemptManagementFixture();
    $this->mock(ExamHallMonitor::class)->shouldReceive('publish')->once()->with('reset');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))
        ->post(route('attempts.clearExam', ['exam' => $data['exams'][0]->uuid, 'search' => 'Ada']), ['confirmed' => true])
        ->assertRedirectToRoute('attempts', ['exam' => $data['exams'][0]->uuid]);
    $this->assertModelMissing($data['attempts'][0]);
    $this->assertModelMissing($data['attempts'][1]);
    $this->assertModelExists($data['attempts'][2]);
    $this->assertModelMissing($data['answer']);
    $this->assertModelMissing($data['hall']);
    $this->assertModelExists($data['report']);
});

test('a student with no attempts in the selected exam cannot clear another students attempts', function () {
    $data = attemptManagementFixture();
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))
        ->post(route('attempts.clearStudent', ['exam' => $data['exams'][1]->uuid, 'student' => $data['students'][1]->uuid]), ['confirmed' => true])->assertNotFound();
    $this->assertDatabaseCount('attempts', 3);
});

test('attempt resets require explicit confirmation', function (string $action) {
    $data = attemptManagementFixture();
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']))
        ->postJson(route($action, ['exam' => $data['exams'][0]->uuid, 'student' => $data['students'][0]->uuid]), ['confirmed' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->assertDatabaseCount('attempts', 3);
})->with(['attempts.clearStudent', 'attempts.clearExam']);

test('attempt management is restricted to active administrators', function () {
    $data = attemptManagementFixture();
    $urls = [route('attempts.clearExam', $data['exams'][0]->uuid), route('attempts.clearStudent', ['exam' => $data['exams'][0]->uuid, 'student' => $data['students'][0]->uuid])];
    $this->get(route('attempts'))->assertRedirect('/login');
    foreach ($urls as $url) {
        $this->post($url, ['confirmed' => true])->assertRedirect('/login');
    }
    $this->actingAs(User::factory()->create(['is_admin' => false, 'status' => 'active']));
    $this->get(route('attempts'))->assertRedirect('/dashboard');
    foreach ($urls as $url) {
        $this->post($url, ['confirmed' => true])->assertRedirect('/dashboard');
    }
    $this->assertDatabaseCount('attempts', 3);
});

test('unknown exam selections and reset URLs cannot fall back to another exam', function () {
    attemptManagementFixture();
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']));
    $this->getJson(route('attempts', ['exam' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('exam');
    $this->post('/dashboard/attempts/unknown/clear', ['confirmed' => true])->assertNotFound();
    $this->assertDatabaseCount('attempts', 3);
});
