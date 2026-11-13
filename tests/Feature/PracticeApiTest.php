<?php

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

function practiceFixture(): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'STD/25/003', 'first_name' => 'Ada', 'middle_name' => '', 'last_name' => 'James', 'gender' => 'Female', 'set' => '2025/2026', 'level' => 'nd']);
    $course = Course::create(['uuid' => (string) str()->uuid(), 'name' => 'Anatomy', 'code' => 'ANA101']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'First semester', 'subtitle' => 'Past exam', 'set' => '2023/2024', 'level' => 'nd', 'start_date' => now()->subMonths(2), 'end_date' => now()->subMonth(), 'status' => 'completed']);
    $paper = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'duration' => 40, 'start' => now()->subMonth()->subHour(), 'end' => now()->subMonth()]);
    $question = Question::create(['single_exam_id' => $paper->id, 'question_text' => '<p>Past question?</p>', 'marks' => 2]);
    $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false]);

    return compact('department', 'student', 'exam', 'paper', 'question', 'correct');
}

function practiceSignIn($test): string
{
    return $test->postJson('/api/practice/login', ['reg_no' => 'STD/25/003'])->assertOk()->json('practice_token');
}

test('practice login lists older sessions and the full selection flow supplies local scoring data without real exam records', function () {
    $fixture = practiceFixture();
    $login = $this->postJson('/api/practice/login', ['reg_no' => $fixture['student']->reg_no])->assertOk()
        ->assertJsonPath('mode', 'practice')->assertJsonPath('student.level', 'nd')->assertJsonPath('student.department.name', 'Community Health')
        ->assertJsonPath('sessions', ['2023/2024'])->assertHeader('Cache-Control', 'no-store, private')->json();
    $this->withToken($login['practice_token']);
    $this->getJson('/api/practice/sessions')->assertOk()->assertJsonPath('sessions', ['2023/2024']);
    $this->getJson('/api/practice/exams?'.http_build_query(['session' => '2023/2024']))->assertOk()->assertJsonCount(1, 'exams')->assertJsonPath('exams.0.uuid', $fixture['exam']->uuid);
    $this->getJson('/api/practice/exams/'.$fixture['exam']->uuid.'/courses')->assertOk()
        ->assertJsonPath('papers.0.uuid', $fixture['paper']->uuid)->assertJsonPath('papers.0.course.name', 'Anatomy')->assertJsonPath('papers.0.questions_count', 1);
    $this->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk()
        ->assertJsonPath('scoring', 'client')->assertJsonPath('paper.course.code', 'ANA101')->assertJsonPath('paper.exam.set', '2023/2024')
        ->assertJsonPath('questions.0.question_text', '<p>Past question?</p>')->assertJsonPath('questions.0.marks', 2)
        ->assertJsonPath('questions.0.options.0.id', $fixture['correct']->id)->assertJsonPath('questions.0.options.0.is_correct', true)
        ->assertJsonPath('questions.0.options.1.is_correct', false)->assertJsonPath('question_count', 1)->assertJsonPath('total_marks', 2);
    foreach (['attempts', 'answers', 'reports', 'exam_halls'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

test('unavailable exam groups are excluded from every practice selection and direct question access', function (string $condition) {
    $fixture = practiceFixture();
    match ($condition) {
        'upcoming' => $fixture['exam']->update(['start_date' => now()->addDay(), 'end_date' => now()->addDays(2), 'status' => 'pending']),
        'future marked completed' => $fixture['exam']->update(['start_date' => now()->addDay(), 'end_date' => now()->addDays(2)]),
        'ongoing group' => $fixture['exam']->update(['start_date' => now()->subDay(), 'end_date' => now()->addDay(), 'status' => 'active']),
        'ongoing paper in completed group' => $fixture['paper']->update(['start' => now()->subMinute(), 'end' => now()->addHour()]),
        'future paper in expired group' => $fixture['paper']->update(['start' => now()->addHour(), 'end' => now()->addHours(2)]),
        'different level' => $fixture['exam']->update(['level' => 'hnd']),
        'different department' => $fixture['exam']->update(['department_id' => Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Other'])->id]),
        'empty paper' => $fixture['question']->delete(),
    };
    $token = practiceSignIn($this);
    $this->withToken($token)->getJson('/api/practice/sessions')->assertOk()->assertJsonPath('sessions', []);
    $this->getJson('/api/practice/exams?'.http_build_query(['session' => '2023/2024']))->assertOk()->assertJsonPath('exams', []);
    $this->getJson('/api/practice/exams/'.$fixture['exam']->uuid.'/courses')->assertNotFound()->assertJsonPath('code', 'PRACTICE_EXAM_UNAVAILABLE');
    $this->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertNotFound()->assertJsonMissingPath('questions');
})->with(['upcoming', 'future marked completed', 'ongoing group', 'ongoing paper in completed group', 'future paper in expired group', 'different level', 'different department', 'empty paper']);

test('expired dates are sufficient even when the scheduler has not marked the exam completed', function () {
    $fixture = practiceFixture();
    $fixture['exam']->update(['status' => 'pending']);
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk();
});

test('completed exams today are available only after all paper windows close', function () {
    $fixture = practiceFixture();
    $fixture['exam']->update(['end_date' => now()]);
    $fixture['paper']->update(['end' => now()]);
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk();
});

test('a still running personal timer or unstarted attempt prevents practice answer key release', function (bool $unstarted) {
    $fixture = practiceFixture();
    $attempt = Attempt::create(['student_id' => $fixture['student']->id, 'single_exam_id' => $fixture['paper']->id, 'status' => 'in_progress', 'started_at' => $unstarted ? null : now()->subMinutes(20), 'expires_at' => $unstarted ? null : now()->addMinutes(20)]);
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertNotFound();
    $attempt->update(['expires_at' => now()->subSeconds(30)]);
    $this->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertNotFound();
    $this->travel(1)->seconds();
    $this->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk();
})->with([false, true]);

test('all protected practice routes reject missing or invalid tokens', function (string $path) {
    $this->getJson($path)->assertUnauthorized()->assertJsonPath('code', 'INVALID_PRACTICE_TOKEN');
    $this->withToken('invalid')->getJson($path)->assertUnauthorized()->assertJsonPath('code', 'INVALID_PRACTICE_TOKEN');
})->with(['/api/practice/sessions', '/api/practice/exams?session=2023%2F2024', '/api/practice/exams/unknown/courses', '/api/practice/papers/unknown/questions']);

test('practice tokens expire and cannot be used after student removal', function () {
    $fixture = practiceFixture();
    $token = practiceSignIn($this);
    $this->travel(8)->hours();
    $this->withToken($token)->getJson('/api/practice/sessions')->assertUnauthorized()->assertJsonPath('code', 'PRACTICE_TOKEN_EXPIRED');
    $token = practiceSignIn($this);
    $fixture['student']->delete();
    $this->withToken($token)->getJson('/api/practice/sessions')->assertUnauthorized()->assertJsonPath('code', 'INVALID_PRACTICE_TOKEN');
});

test('student level is checked from current records rather than trusted from client input', function () {
    $fixture = practiceFixture();
    $token = practiceSignIn($this);
    $fixture['student']->update(['level' => 'hnd']);
    $this->withToken($token)->getJson('/api/practice/sessions?level=nd&department_id='.$fixture['department']->id)->assertOk()->assertJsonPath('sessions', []);
    $fixture['exam']->update(['level' => 'hnd']);
    $this->getJson('/api/practice/sessions')->assertOk()->assertJsonPath('sessions', ['2023/2024']);
});

test('practice login validates registration and requires an ND or HND assignment', function () {
    $this->postJson('/api/practice/login', [])->assertUnprocessable()->assertJsonValidationErrors('reg_no');
    $this->postJson('/api/practice/login', ['reg_no' => 'UNKNOWN'])->assertNotFound()->assertJsonPath('code', 'STUDENT_NOT_FOUND');
    $fixture = practiceFixture();
    $fixture['student']->update(['level' => '100']);
    $this->postJson('/api/practice/login', ['reg_no' => $fixture['student']->reg_no])->assertForbidden()->assertJsonPath('code', 'PRACTICE_LEVEL_UNAVAILABLE');
});

test('practice authentication rejects other encrypted token purposes and session selection requires a value', function () {
    $fixture = practiceFixture();
    $other = Crypt::encryptString(json_encode(['purpose' => 'exam', 'student_id' => $fixture['student']->id, 'expires_at' => now()->addHour()->timestamp], JSON_THROW_ON_ERROR));
    $this->withToken($other)->getJson('/api/practice/sessions')->assertUnauthorized();
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/exams')->assertUnprocessable()->assertJsonValidationErrors('session');
});

test('a paper rescheduled after selection becomes unavailable before its answers can be downloaded', function () {
    $fixture = practiceFixture();
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/exams/'.$fixture['exam']->uuid.'/courses')->assertOk();
    $fixture['paper']->update(['start' => now()->addDay(), 'end' => now()->addDay()->addHour()]);
    $this->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertNotFound()->assertJsonMissingPath('questions');
});

test('practice and real exam credentials are isolated and real question responses still hide answer keys', function () {
    $fixture = practiceFixture();
    $practiceToken = practiceSignIn($this);
    $fixture['exam']->update(['set' => $fixture['student']->set, 'start_date' => now()->subDay(), 'end_date' => now()->addDay(), 'status' => 'active']);
    $fixture['paper']->update(['start' => now()->subMinute(), 'end' => now()->addHour()]);
    $login = $this->postJson('/api/student-login', ['reg_no' => $fixture['student']->reg_no, 'client' => 'flutter', 'login_id' => (string) str()->uuid()])->assertOk()->assertJsonPath('type', 'start')->json();
    $url = '/api/load-questions/'.$fixture['department']->id.'/2025-2026/'.$login['attempt_id'];
    $this->withToken($practiceToken)->getJson($url)->assertUnauthorized()->assertJsonMissingPath('questions');
    $this->withToken($login['attempt_token'])->getJson('/api/practice/sessions')->assertUnauthorized();
    $this->withToken($login['attempt_token'])->getJson($url)->assertOk()->assertJsonMissingPath('questions.0.options.0.is_correct');
});

test('practice downloads the entire paper without the real examination allocation limit', function () {
    $fixture = practiceFixture();
    for ($index = 0; $index < 60; $index++) {
        $question = Question::create(['single_exam_id' => $fixture['paper']->id, 'question_text' => 'Past question '.$index, 'marks' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    }
    $this->withToken(practiceSignIn($this))->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk()
        ->assertJsonCount(61, 'questions')->assertJsonPath('question_count', 61)->assertJsonPath('total_marks', 62);
    $this->assertDatabaseCount('attempts', 0);
});

test('practice accepts new database levels and returns their configured display name', function () {
    $fixture = practiceFixture();
    \App\Models\Level::create(['code' => 'advanced', 'name' => 'Advanced Diploma']);
    $fixture['student']->update(['level' => 'advanced']);
    $fixture['exam']->update(['level' => 'advanced']);
    $login = $this->postJson('/api/practice/login', ['reg_no' => $fixture['student']->reg_no])->assertOk()
        ->assertJsonPath('student.level', 'advanced')->assertJsonPath('student.level_name', 'Advanced Diploma')
        ->assertJsonPath('sessions', ['2023/2024'])->json();
    $this->withToken($login['practice_token'])->getJson('/api/practice/papers/'.$fixture['paper']->uuid.'/questions')->assertOk();
    \App\Models\Level::where('code', 'advanced')->delete();
    $this->getJson('/api/practice/sessions')->assertForbidden()->assertJsonPath('code', 'PRACTICE_LEVEL_UNAVAILABLE');
});
