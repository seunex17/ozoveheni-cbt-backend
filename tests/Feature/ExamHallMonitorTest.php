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

    return [...$fixture, 'attempt' => Attempt::findOrFail($response->json('attempt_id')), 'token' => $response->json('hall_token')];
}

test('login issues a hashed attempt specific hall token and publishes the roster change', function () {
    Event::fake([ExamHallUpdated::class, ExamHallEvent::class]);
    $fixture = hallLogin($this);

    expect($fixture['token'])->toHaveLength(64);
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'monitor_token_hash' => hash('sha256', $fixture['token'])]);
    expect(ExamHall::first()->toArray())->not->toHaveKey('monitor_token_hash');
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'refresh');
    Event::assertDispatched(ExamHallEvent::class, fn ($event) => $event->totalStudent === 1);
});

test('heartbeat saves the reported position and older requests cannot replace it', function () {
    $this->freezeTime();
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    $payload = [
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 2,
        'current_question_id' => $fixture['question']->id,
        'current_question_number' => 1, 'total_questions' => 1,
    ];

    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', $payload)
        ->assertOk()->assertJsonPath('accepted', true)->assertJsonPath('last_sequence', 2);
    $seen = ExamHall::first()->last_seen_at;
    $this->travel(70)->seconds();
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', [
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 1,
    ])->assertOk()->assertJsonPath('accepted', false)->assertJsonPath('last_sequence', 2);

    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'current_question_number' => 1, 'last_sequence' => 2]);
    expect(ExamHall::first()->last_seen_at->equalTo($seen))->toBeTrue();
    Event::assertDispatchedTimes(ExamHallUpdated::class, 1);
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'progress' && $event->progress['current_question_number'] === 1 && $event->broadcastOn()->name === 'private-exam-hall.monitor');
});

test('heartbeat returns 401 without the attempt token and does not change another student', function () {
    $fixture = hallLogin($this);
    $this->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 1])->assertUnauthorized();
    $this->withToken('wrong-token')->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 1])->assertUnauthorized();
    $other = Attempt::create(['student_id' => $fixture['student']->id, 'single_exam_id' => $fixture['paper']->id]);
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $other->id, 'sequence' => 1])->assertUnauthorized();
    $this->assertDatabaseHas('exam_halls', ['attempt_id' => $fixture['attempt']->id, 'last_sequence' => 0, 'last_seen_at' => null]);
});

test('heartbeat returns 422 for invalid positions or questions outside the paper', function (array $overrides) {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    $payload = array_replace([
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 1,
        'current_question_id' => $fixture['question']->id, 'current_question_number' => 1, 'total_questions' => 1,
    ], $overrides);

    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', $payload)->assertUnprocessable();
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

test('a heartbeat without a question refreshes presence without inventing progress', function () {
    $fixture = hallLogin($this);
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 1])->assertOk()->assertJsonPath('accepted', true);
    expect(ExamHall::first()->last_seen_at)->not->toBeNull();
    expect(ExamHall::first()->current_question_number)->toBeNull();
});

test('staff snapshot exposes profiles and progress but excludes completed attempts and secrets', function () {
    $fixture = hallLogin($this);
    $completed = Attempt::create(['student_id' => $fixture['student']->id, 'single_exam_id' => $fixture['paper']->id, 'status' => 'completed', 'submitted_at' => now()]);
    ExamHall::create(['attempt_id' => $completed->id, 'student_id' => $fixture['student']->id]);
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);

    $this->actingAs($staff)->get('/dashboard/exam-hall')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/ExamHall/Index'));
    $this->getJson('/dashboard/exam-hall/snapshot')->assertOk()->assertJsonPath('count', 1)
        ->assertJsonPath('participants.0.student.name', 'Ada Test Student')
        ->assertJsonPath('participants.0.current_question_number', null)
        ->assertJsonPath('participants.0.last_seen_at', null)
        ->assertJsonPath('participants.0.exam.code', 'CHS101')
        ->assertJsonMissingPath('participants.0.monitor_token_hash')
        ->assertDontSee($fixture['token'])->assertHeader('Cache-Control', 'no-store, private');
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

test('submission removes the student from the roster and rejects delayed heartbeats with 409', function () {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class, ExamHallEvent::class]);
    $this->postJson('/api/submit-exam', ['attempt_id' => $fixture['attempt']->id])->assertOk();
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 1])->assertConflict();
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->getJson('/dashboard/exam-hall/snapshot')->assertOk()->assertJsonPath('count', 0);
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'remove' && $event->attempt_id === $fixture['attempt']->id);
    Event::assertDispatched(ExamHallEvent::class, fn ($event) => $event->totalStudent === 0);
});

test('sweeping the hall revokes telemetry tokens and publishes a reset', function () {
    $fixture = hallLogin($this);
    Event::fake([ExamHallUpdated::class]);
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->post('/dashboard/sweep-exam-hall')->assertRedirect();
    $this->assertDatabaseCount('exam_halls', 0);
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', ['attempt_id' => $fixture['attempt']->id, 'sequence' => 2])->assertUnauthorized();
    Event::assertDispatched(ExamHallUpdated::class, fn ($event) => $event->operation === 'reset');
});

test('presence refresh preserves the current question and ignores client supplied identity and timestamps', function () {
    $this->travelTo(now()->startOfSecond());
    $fixture = hallLogin($this);
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', [
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 1,
        'current_question_id' => $fixture['question']->id, 'current_question_number' => 1, 'total_questions' => 1,
    ])->assertOk();
    $this->travel(75)->seconds();
    $this->withToken($fixture['token'])->postJson('/api/exam-hall/heartbeat', [
        'attempt_id' => $fixture['attempt']->id, 'sequence' => 2,
        'student_id' => 999, 'last_seen_at' => '2099-01-01T00:00:00Z',
    ])->assertOk()->assertJsonPath('accepted', true);

    $this->assertDatabaseHas('exam_halls', [
        'attempt_id' => $fixture['attempt']->id, 'student_id' => $fixture['student']->id,
        'current_question_number' => 1, 'last_sequence' => 2,
    ]);
    expect(ExamHall::first()->last_seen_at->equalTo(now()))->toBeTrue();
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseCount('reports', 0);
});
