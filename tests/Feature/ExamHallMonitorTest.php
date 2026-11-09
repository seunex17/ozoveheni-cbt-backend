<?php

use App\Events\ExamHallEvent;
use App\Events\ExamHallUpdated;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\Question;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function hallExamFixture(): array
{
    $department = Department::create(['name' => 'Community Health', 'uuid' => str()->uuid()]);
    $student = Student::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id,
        'reg_no' => 'HALL/001', 'first_name' => 'Ada', 'middle_name' => 'Test', 'last_name' => 'Student',
        'set' => '2024', 'gender' => 'Female', 'level' => '100', 'photo' => 'students/ada.jpg',
    ]);
    $course = Course::forceCreate(['uuid' => str()->uuid(), 'name' => 'Primary Health Care', 'code' => 'CHS101']);
    $exam = Exam::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id,
        'title' => 'Semester examination', 'set' => '2024', 'level' => '100',
        'start_date' => now()->startOfDay(), 'end_date' => now()->addDay(),
    ]);
    $paper = SingleExam::forceCreate([
        'uuid' => str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id,
        'start' => now()->subMinute(), 'end' => now()->addHour(), 'duration' => 60,
    ]);
    $question = Question::create(['single_exam_id' => $paper->id, 'question_text' => 'Question', 'marks' => 2]);

    return compact('student', 'paper', 'question');
}

function hallLogin($test): array
{
    $fixture = hallExamFixture();
    $response = $test->postJson('/api/student-login', ['reg_no' => $fixture['student']->reg_no])->assertOk()->assertJsonPath('type', 'start');

    return [...$fixture, 'attempt' => Attempt::findOrFail($response->json('attempt_id')), 'login_response' => $response->json()];
}

test('login publishes an ongoing profile without issuing heartbeat credentials', function () {
    Event::fake([ExamHallUpdated::class, ExamHallEvent::class]);
    $fixture = hallLogin($this);
    expect($fixture['login_response']['hall_token'])->toBeNull();
    expect($fixture['login_response']['hall_heartbeat_required'])->toBeFalse();
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'monitor_token_hash' => null]);
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'upsert'
        && $event->participant['status'] === 'ongoing'
        && $event->participant['student']['name'] === 'Ada Test Student'
        && $event->broadcastOn()->name === 'private-exam-hall.monitor');
    Event::assertDispatched(ExamHallEvent::class, fn ($event) => $event->totalStudent === 1);
});

test('retired heartbeat performs no database queries writes or broadcasts', function () {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    \Illuminate\Support\Facades\DB::enableQueryLog();
    try {
        $this->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 2])
            ->assertGone()->assertJsonPath('code', 'HALL_HEARTBEAT_RETIRED')->assertJsonPath('heartbeat_required', false);
        expect(\Illuminate\Support\Facades\DB::getQueryLog())->toBe([]);
    } finally {
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'last_sequence' => 0, 'last_seen_at' => null]);
    Event::assertNotDispatched(ExamHallUpdated::class);
});

test('retired heartbeat reveals no attempt details for missing or incorrect credentials', function () {
    $fixture = hallLogin($this);
    $this->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id])->assertGone();
    $this->withToken('wrong-token')->postJson('/api/exam-hall/heartbeat', ['attempt_id' => 99999])->assertGone()->assertJsonMissingPath('student');
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'last_seen_at' => null]);
});

test('retired question payloads consistently tell old clients to stop rather than retry validation', function (array $overrides) {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    $payload = array_replace([
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 1,
        'current_question_id' => $fixture['question']->id, 'current_question_number' => 1, 'total_questions' => 1,
    ], $overrides);
    $this->postJson('/api/exam-hall/heartbeat', $payload)->assertGone()->assertJsonPath('heartbeat_required', false);
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'last_sequence' => 0]);
    Event::assertNotDispatched(ExamHallUpdated::class);
})->with([
    'question not in paper' => [['current_question_id' => 99999]],
    'position beyond paper' => [['current_question_number' => 2]],
    'exaggerated paper length' => [['total_questions' => 2]],
    'zero position' => [['current_question_number' => 0]],
    'missing sequence' => [['sequence' => null]],
    'incomplete progress' => [['current_question_id' => null]],
]);

test('presence only heartbeats are retired and do not invent connection freshness', function () {
    $fixture = hallLogin($this);
    $this->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 1])->assertGone();
    expect(ExamHall::first()->last_seen_at)->toBeNull();
});

test('staff snapshot retains submitted students but exposes only identity and examination status', function () {
    $fixture = hallLogin($this);
    $completed = Attempt::create(['student_id' => $fixture['student']->id, 'single_exam_id' => $fixture['paper']->id, 'status' => 'completed', 'submitted_at' => now()]);
    ExamHall::create(['attempt_id' => $completed->id, 'student_id' => $fixture['student']->id]);
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/exam-hall')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/ExamHall/Index'));
    $this->getJson('/dashboard/exam-hall/snapshot')->assertOk()->assertJsonCount(2, 'participants')
        ->assertJsonPath('count', 1)->assertJsonPath('submitted_count', 1)
        ->assertJsonPath('participants.0.student.name', 'Ada Test Student')
        ->assertJsonPath('participants.0.status', 'ongoing')->assertJsonPath('participants.1.status', 'submitted')
        ->assertJsonMissingPath('participants.0.current_question_number')->assertJsonMissingPath('participants.0.last_seen_at')
        ->assertJsonMissingPath('participants.0.monitor_token_hash')->assertJsonMissingPath('participants.0.exam')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('guest and inactive staff cannot read the hall roster', function () {
    $this->getJson('/dashboard/exam-hall/snapshot')->assertUnauthorized();
    $inactive = User::factory()->create(['status' => 'inactive']);
    $this->actingAs($inactive)->get('/dashboard/exam-hall')->assertRedirect('/');
    $this->actingAs($inactive)->getJson('/dashboard/exam-hall/snapshot')->assertRedirect('/');
});

test('private monitor channel refuses guests and inactive staff but accepts active staff', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    require base_path('routes/channels.php');
    $payload = ['channel_name' => 'private-exam-hall.monitor', 'socket_id' => '123.456'];
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $inactive = User::factory()->create(['status' => 'inactive']);
    $this->actingAs($inactive)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $active = User::factory()->create(['status' => 'active']);
    $this->actingAs($active)->postJson('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
});

test('submission keeps the student visible with a submitted badge and retries do not broadcast again', function () {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class, ExamHallEvent::class]);
    $this->postJson('/api/submit-exam', ['attempt_id' => $fixture['attempt']->id])->assertOk();
    $this->postJson('/api/submit-exam', ['attempt_id' => $fixture['attempt']->id])->assertOk();
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->getJson('/dashboard/exam-hall/snapshot')->assertOk()->assertJsonPath('count', 0)
        ->assertJsonPath('submitted_count', 1)->assertJsonPath('participants.0.status', 'submitted');
    Event::assertDispatchedTimes(ExamHallUpdated::class, 1);
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'upsert' && $event->participant['status'] === 'submitted');
    Event::assertDispatched(ExamHallEvent::class, fn ($event) => $event->totalStudent === 0);
});

test('sweeping clears ongoing and submitted cards and publishes a reset', function () {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->post('/dashboard/sweep-exam-hall')->assertRedirect();
    $this->assertDatabaseCount('exam_halls', 0);
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'reset');
});

test('answer saving does not generate repeated hall broadcasts', function () {
    $fixture = hallLogin($this);
    $option = \App\Models\QuestionOption::create(['question_id' => $fixture['question']->id, 'option_text' => 'A', 'is_correct' => true]);
    Event::fake([ExamHallUpdated::class]);
    $payload = ['attempt_id' => $fixture['attempt']->id, 'answers' => [$fixture['question']->id => $option->id]];
    $this->postJson('/api/save-answers', $payload)->assertOk();
    $this->postJson('/api/save-answers', $payload)->assertOk();
    Event::assertNotDispatched(ExamHallUpdated::class);
});
