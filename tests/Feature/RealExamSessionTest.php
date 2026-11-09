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

uses(RefreshDatabase::class);

function realExamFixture(): array
{
    $department = Department::create(['name' => 'Test department', 'uuid' => str()->uuid()]);
    $student = Student::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'REG/'.str()->uuid(),
        'first_name' => 'Ada', 'last_name' => 'Student', 'set' => '2026/2027', 'gender' => 'Female', 'level' => '100',
    ]);
    $course = Course::forceCreate(['uuid' => str()->uuid(), 'name' => 'Anatomy', 'code' => 'ANA101']);
    $exam = Exam::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id, 'title' => 'Semester exam',
        'set' => $student->set, 'level' => '100', 'start_date' => now()->startOfDay(), 'end_date' => now()->addDay(),
    ]);
    $paper = SingleExam::forceCreate([
        'uuid' => str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id,
        'start' => now()->startOfDay()->addHours(8), 'end' => now()->startOfDay()->addHours(12), 'duration' => 40,
    ]);
    $question = Question::create(['single_exam_id' => $paper->id, 'question_text' => 'Question', 'marks' => 2]);
    $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true]);
    $wrong = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false]);
    $login = ['reg_no' => $student->reg_no, 'client' => 'flutter', 'login_id' => (string) str()->uuid()];

    return compact('department', 'student', 'paper', 'question', 'correct', 'wrong', 'login');
}

function startRealExam($test, array $fixture): array
{
    $login = $test->postJson('/api/student-login', $fixture['login'])->assertOk()->assertJsonPath('type', 'start')->json();
    $url = '/api/load-questions/'.$fixture['department']->id.'/2026-2027/'.$login['attempt_id'];
    $loaded = $test->withToken($login['attempt_token'])->getJson($url)->assertOk()->json();

    return [...$login, 'url' => $url, 'loaded' => $loaded];
}

test('each admitted student receives the full duration beyond the admission window and stable paper order', function () {
    $this->travelTo(now()->startOfDay()->addHours(11)->addMinutes(50));
    $fixture = realExamFixture();
    for ($i = 0; $i < 65; $i++) {
        $question = Question::create(['single_exam_id' => $fixture['paper']->id, 'question_text' => 'Question '.$i, 'marks' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'A', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'B', 'is_correct' => false]);
    }
    $session = startRealExam($this, $fixture);
    expect($session['loaded']['remaining_seconds'])->toBe(2400);
    expect($session['loaded']['expires_at'])->toBe(now()->startOfDay()->addHours(12)->addMinutes(30)->toISOString());
    expect($session['loaded']['questions'])->toHaveCount(60);
    $fixture['paper']->update(['duration' => 120]);
    $this->travel(20)->minutes();

    $reloaded = $this->withToken($session['attempt_token'])->getJson($session['url'])->assertOk()->assertJsonPath('remaining_seconds', 1200)->json();
    expect($reloaded['questions'])->toBe($session['loaded']['questions']);
    expect($reloaded['expires_at'])->toBe($session['loaded']['expires_at']);
    expect($reloaded['questions'][0]['options'][0])->not->toHaveKey('is_correct');
});

test('login recovery returns the same attempt and token after a lost response and after completion', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->travel(3)->hours();
    $this->postJson('/api/student-login', $fixture['login'])->assertOk()
        ->assertJsonPath('attempt_id', $session['attempt_id'])->assertJsonPath('attempt_token', $session['attempt_token']);
    $payload = ['attempt_id' => $session['attempt_id'], 'answers' => [], 'submission_id' => (string) str()->uuid()];
    $this->withToken($session['attempt_token'])->postJson('/api/submit-exam', $payload)->assertOk();
    $this->postJson('/api/student-login', $fixture['login'])->assertOk()
        ->assertJsonPath('attempt_status', 'completed')->assertJsonPath('receipt.submission_id', $payload['submission_id']);
    $this->assertDatabaseCount('attempts', 1);
});

test('new admissions stop exactly at the scheduled end', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $fixture = realExamFixture();
    $this->postJson('/api/student-login', $fixture['login'])->assertOk()->assertJsonPath('type', 'error');
    $this->assertDatabaseCount('attempts', 0);
});

test('protected attempts reject missing or wrong tokens on load save submit resume and status', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->withToken('another-attempt-token');
    $this->getJson($session['url'])->assertUnauthorized();
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => []])->assertUnauthorized();
    $this->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id']])->assertUnauthorized();
    $this->postJson('/api/resume-exam', ['attempt_id' => $session['attempt_id']])->assertUnauthorized();
    $this->getJson('/api/attempt-status/'.$session['attempt_id'])->assertUnauthorized();
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseHas('attempts', ['id' => $session['attempt_id'], 'status' => 'in_progress']);
});

test('resume restores saved answers and time after admission closes without restarting the clock', function () {
    $this->travelTo(now()->startOfDay()->addHours(11)->addMinutes(50));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $answers = [$fixture['question']->id => $fixture['correct']->id];
    $this->withToken($session['attempt_token'])->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => $answers])->assertOk();
    $this->travel(20)->minutes();
    $this->postJson('/api/resume-exam', ['attempt_id' => $session['attempt_id']])->assertOk()
        ->assertJsonPath('remaining_seconds', 1200)->assertJsonPath('answers.'.$fixture['question']->id, $fixture['correct']->id)
        ->assertJsonPath('questions', $session['loaded']['questions']);
    $this->getJson('/api/attempt-status/'.$session['attempt_id'])->assertOk()->assertJsonMissingPath('access_token_hash')->assertJsonMissingPath('login_key_hash');
});

test('empty saves and zero answer submissions succeed and create a zero score report', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->withToken($session['attempt_token'])->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => []])->assertOk()->assertJsonPath('success', true);
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id']])->assertUnprocessable();
    $this->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'answers' => [], 'submission_id' => (string) str()->uuid()])->assertOk();
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 0]);
});

test('autosaves stop at expiry but final answers are accepted through the agreed thirty second grace period', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $answers = [$fixture['question']->id => $fixture['correct']->id];
    $this->travel(40)->minutes();
    $this->withToken($session['attempt_token'])->getJson('/api/attempt-status/'.$session['attempt_id'])->assertOk()->assertJsonPath('remaining_seconds', 0);
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => $answers])->assertConflict()->assertJsonPath('code', 'ATTEMPT_EXPIRED');
    $this->travel(30)->seconds();
    $this->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'answers' => $answers, 'submission_id' => (string) str()->uuid()])->assertOk();
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 2]);
});

test('late unsaved answers are rejected and explicit saved only submission preserves the acknowledged score', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->withToken($session['attempt_token'])->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => [$fixture['question']->id => $fixture['wrong']->id]])->assertOk();
    $this->travel(40)->minutes();
    $this->travel(31)->seconds();
    $id = (string) str()->uuid();
    $this->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'answers' => [$fixture['question']->id => $fixture['correct']->id], 'submission_id' => $id])
        ->assertConflict()->assertJsonPath('code', 'FINAL_DELIVERY_EXPIRED');
    $this->assertDatabaseHas('attempts', ['id' => $session['attempt_id'], 'status' => 'in_progress']);
    $this->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'submit_saved_answers' => true, 'submission_id' => $id])->assertOk();
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 0]);
    $this->assertDatabaseHas('answers', ['attempt_id' => $session['attempt_id'], 'option_id' => $fixture['wrong']->id]);
});

test('submission receipts survive retries and reject conflicting ids or changed snapshots', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $payload = ['attempt_id' => $session['attempt_id'], 'answers' => [$fixture['question']->id => $fixture['correct']->id], 'submission_id' => (string) str()->uuid()];
    $receipt = $this->withToken($session['attempt_token'])->postJson('/api/submit-exam', $payload)->assertOk()->json();
    $this->travel(2)->hours();
    $this->postJson('/api/submit-exam', $payload)->assertOk()->assertExactJson($receipt);
    $this->postJson('/api/submit-exam', [...$payload, 'submission_id' => (string) str()->uuid()])->assertConflict()->assertJsonPath('code', 'SUBMISSION_CONFLICT');
    $this->postJson('/api/submit-exam', [...$payload, 'answers' => []])->assertConflict()->assertJsonPath('code', 'SUBMISSION_CONFLICT');
    $this->getJson('/api/attempt-status/'.$session['attempt_id'])->assertOk()->assertJsonPath('receipt', $receipt);
    $this->assertDatabaseCount('reports', 1);
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 2]);
});

test('unchanged completed saves acknowledge retries but changed completed saves cannot alter marks', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $answers = [$fixture['question']->id => $fixture['correct']->id];
    $this->withToken($session['attempt_token'])->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'answers' => $answers, 'submission_id' => (string) str()->uuid()])->assertOk();
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => $answers])->assertOk()->assertJsonPath('completed', true);
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => [$fixture['question']->id => null]])->assertConflict();
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 2]);
});

test('allocation rejects later added questions and options without changing stored answers', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $question = Question::create(['single_exam_id' => $fixture['paper']->id, 'question_text' => 'Added later', 'marks' => 5]);
    $option = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'A', 'is_correct' => true]);
    $this->withToken($session['attempt_token'])->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => [$question->id => $option->id]])->assertUnprocessable();
    $option = QuestionOption::create(['question_id' => $fixture['question']->id, 'option_text' => 'Added later', 'is_correct' => true]);
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => [$fixture['question']->id => $option->id]])->assertUnprocessable();
    $this->assertDatabaseCount('answers', 0);
});

test('a missing allocated option fails clearly instead of allocating a new paper on resume', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $fixture['correct']->delete();
    $this->withToken($session['attempt_token'])->postJson('/api/resume-exam', ['attempt_id' => $session['attempt_id']])->assertConflict()->assertJsonPath('code', 'EXAM_PAPER_CHANGED');
    expect(Attempt::find($session['attempt_id'])->expires_at->toISOString())->toBe($session['loaded']['expires_at']);
});

test('admission and resume before the first paper load do not consume answering time', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $login = $this->postJson('/api/student-login', $fixture['login'])->assertOk()->assertJsonPath('remaining_seconds', null)->json();
    $this->travel(20)->minutes();
    $this->withToken($login['attempt_token'])->postJson('/api/resume-exam', ['attempt_id' => $login['attempt_id']])->assertOk()
        ->assertJsonPath('started_at', null)->assertJsonPath('questions', []);
    $url = '/api/load-questions/'.$fixture['department']->id.'/2026-2027/'.$login['attempt_id'];
    $this->getJson($url)->assertOk()->assertJsonPath('remaining_seconds', 2400);
});

test('a completed student cannot create a fresh attempt with a new login id', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->withToken($session['attempt_token'])->postJson('/api/submit-exam', ['attempt_id' => $session['attempt_id'], 'answers' => [], 'submission_id' => (string) str()->uuid()])->assertOk();
    $this->postJson('/api/student-login', [...$fixture['login'], 'login_id' => (string) str()->uuid()])->assertOk()->assertJsonPath('type', 'error');
    $this->assertDatabaseCount('attempts', 1);
});

test('URL scope cannot change a students paper and API validation returns JSON without an Accept header', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->withToken($session['attempt_token'])->getJson('/api/load-questions/999/2026-2027/'.$session['attempt_id'])->assertNotFound();
    $this->getJson(str_replace('2026-2027', '2099-2100', $session['url']))->assertNotFound();
    $this->post('/api/save-answers', ['attempt_id' => $session['attempt_id']])->assertUnprocessable()->assertJsonValidationErrors('answers');
});

test('equivalent UUID casing recovers the same attempt and repeated receipt', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $this->postJson('/api/student-login', [...$fixture['login'], 'login_id' => strtoupper($fixture['login']['login_id'])])->assertOk()->assertJsonPath('attempt_id', $session['attempt_id']);
    $id = (string) str()->uuid();
    $payload = ['attempt_id' => $session['attempt_id'], 'answers' => [], 'submission_id' => $id];
    $receipt = $this->withToken($session['attempt_token'])->postJson('/api/submit-exam', $payload)->assertOk()->json();
    $this->postJson('/api/submit-exam', [...$payload, 'submission_id' => strtoupper($id)])->assertOk()->assertExactJson($receipt);
});

test('live question telemetry must match the persisted allocation and displayed ordinal', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $payload = ['attempt_id' => $session['attempt_id'], 'sequence' => 1, 'current_question_id' => $fixture['question']->id, 'current_question_number' => 1, 'total_questions' => 1];
    $this->withToken($session['hall_token'])->postJson('/api/exam-hall/heartbeat', $payload)->assertOk();
    $this->postJson('/api/exam-hall/heartbeat', [...$payload, 'sequence' => 2, 'total_questions' => 2])->assertUnprocessable();
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $session['attempt_id'], 'last_sequence' => 1]);
});

test('noncanonical question keys cannot create duplicate answer rows or inflate marks', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $fixture = realExamFixture();
    $session = startRealExam($this, $fixture);
    $answers = [$fixture['question']->id => $fixture['correct']->id];
    $this->withToken($session['attempt_token'])->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => $answers])->assertOk();
    $this->postJson('/api/save-answers', ['attempt_id' => $session['attempt_id'], 'answers' => ['0'.$fixture['question']->id => $fixture['correct']->id]])->assertUnprocessable();
    $this->assertDatabaseCount('answers', 1);
    $this->assertDatabaseHas('reports', ['student_id' => $fixture['student']->id, 'exam' => 2]);
});

test('staff cannot configure a fractional zero or negative examination duration', function ($duration) {
    $staff = \App\Models\User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($staff)->post('/dashboard/exam/set-new-exam', [
        'exam_id' => 1, 'course_id' => 1, 'schedule_date' => now()->toDateString(),
        'start_time' => '08:00', 'end_time' => '12:00', 'duration' => $duration,
    ])->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseCount('single_exams', 0);
})->with([0, -1, 1.5]);
