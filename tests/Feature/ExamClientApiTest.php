<?php

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $department = Department::create(['name' => 'Test', 'uuid' => str()->uuid()]);
    $student = Student::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id,
        'reg_no' => 'REG/001', 'first_name' => 'Test', 'last_name' => 'Student',
        'set' => '2024', 'gender' => 'Male', 'level' => '100',
    ]);
    $course = Course::forceCreate(['uuid' => str()->uuid(), 'name' => 'Test', 'code' => 'TEST']);
    $exam = Exam::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id,
        'title' => 'Test', 'set' => '2024', 'level' => '100',
        'start_date' => now(), 'end_date' => now()->addDay(),
    ]);
    $paper = SingleExam::forceCreate([
        'uuid' => str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id,
        'start' => now(), 'end' => now()->addHour(), 'duration' => 60,
    ]);
    $this->attempt = Attempt::create(['student_id' => $student->id, 'single_exam_id' => $paper->id]);
    $this->question = Question::create(['single_exam_id' => $paper->id, 'question_text' => 'Question', 'marks' => 2]);
    $this->option = QuestionOption::create(['question_id' => $this->question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    $this->payload = ['attempt_id' => $this->attempt->id, 'answers' => [$this->question->id => $this->option->id]];
});

test('autosave skips unchanged writes and preserves grading', function () {
    $this->postJson('/api/save-answers', $this->payload)->assertOk();
    $answer = Answer::first();
    $report = Report::first();
    expect((int) $report->exam)->toBe(2);
    $this->travel(10)->seconds();
    $this->postJson('/api/save-answers', $this->payload)->assertOk();
    expect(Answer::count())->toBe(1)
        ->and(Answer::first()->updated_at->equalTo($answer->updated_at))->toBeTrue()
        ->and(Report::first()->updated_at->equalTo($report->updated_at))->toBeTrue();
});

test('submission commits final answers and retries cannot change completion or marks', function () {
    $this->postJson('/api/submit-exam', $this->payload)->assertOk();
    $submitted = $this->attempt->fresh()->submitted_at;
    $this->travel(10)->seconds();
    $cleared = ['attempt_id' => $this->attempt->id, 'answers' => [$this->question->id => null]];
    $this->postJson('/api/submit-exam', $cleared)->assertOk();
    $this->postJson('/api/save-answers', $cleared)->assertStatus(409);
    expect($this->attempt->fresh()->submitted_at->equalTo($submitted))->toBeTrue()
        ->and((int) Report::first()->exam)->toBe(2);
});

test('invalid option question pairs roll back submission', function () {
    $other = Question::create(['single_exam_id' => $this->attempt->single_exam_id, 'question_text' => 'Other', 'marks' => 5]);
    $this->payload['answers'] = [$other->id => $this->option->id];
    $this->postJson('/api/submit-exam', $this->payload)->assertUnprocessable();
    expect(Answer::count())->toBe(0)->and($this->attempt->fresh()->status)->toBe('in_progress');
});

test('explicit null clears an answer and legacy submission remains accepted', function () {
    $this->postJson('/api/save-answers', $this->payload)->assertOk();
    $this->payload['answers'][$this->question->id] = null;
    $this->postJson('/api/save-answers', $this->payload)->assertOk();
    expect(Answer::first()->option_id)->toBeNull()->and((int) Report::first()->exam)->toBe(0);
    $this->postJson('/api/submit-exam', ['attempt_id' => $this->attempt->id])->assertOk();
});

test('paper loading hides answer keys and preserves the original start time', function () {
    $paper = $this->attempt->singleExam;
    $exam = $paper->exam;
    $url = '/api/load-questions/'.$exam->department_id.'/'.$exam->set.'/'.$this->attempt->id;
    $this->getJson($url)->assertOk()->assertJsonMissingPath('questions.0.options.0.is_correct');
    $started = $this->attempt->fresh()->started_at;
    $this->travel(10)->seconds();
    $this->getJson($url)->assertOk();
    expect($this->attempt->fresh()->started_at->equalTo($started))->toBeTrue();
    $this->postJson('/api/submit-exam', ['attempt_id' => $this->attempt->id])->assertOk();
    $this->getJson($url)->assertStatus(409);
});

test('answers from another paper are rejected without writes', function () {
    $paper = $this->attempt->singleExam->replicate();
    $paper->uuid = str()->uuid();
    $paper->save();
    $question = Question::create(['single_exam_id' => $paper->id, 'question_text' => 'Other paper', 'marks' => 2]);
    $option = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    $this->payload['answers'] = [$question->id => $option->id];
    $this->postJson('/api/save-answers', $this->payload)->assertUnprocessable();
    expect(Answer::count())->toBe(0);
});
