<?php

use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function auditFixture(): array
{
    $admin = User::factory()->create(['name' => 'Exam Administrator', 'is_admin' => true, 'status' => 'active']);
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
    $course = Course::create(['uuid' => (string) str()->uuid(), 'name' => 'Anatomy', 'code' => 'ANA101']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'First semester', 'set' => '2025/2026', 'level' => 'nd', 'start_date' => '2026-11-12', 'end_date' => '2026-11-14']);
    $paper = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'start' => '2026-11-12 08:00:00', 'end' => '2026-11-12 12:00:00', 'duration' => 40]);
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'STD/25/003', 'set' => $exam->set, 'level' => 'nd', 'first_name' => 'Ada', 'middle_name' => '', 'last_name' => 'James', 'gender' => 'Female']);
    $attempt = Attempt::create(['student_id' => $student->id, 'single_exam_id' => $paper->id, 'access_token_hash' => hash('sha256', 'private-attempt-token')]);
    $report = Report::create(['exam_id' => $exam->id, 'course_id' => $course->id, 'student_id' => $student->id, 'first_ca' => 10, 'second_ca' => 12, 'exam' => 40, 'total_ca' => 22]);

    return compact('admin', 'department', 'course', 'exam', 'paper', 'student', 'attempt', 'report');
}

function auditExamInput(array $fixture): array
{
    return ['id' => $fixture['exam']->id, 'department_id' => $fixture['department']->id, 'title' => 'First semester', 'subtitle' => '', 'set' => '2025/2026', 'level' => 'nd', 'start_date' => '2026-11-13', 'end_date' => '2026-11-15'];
}

test('schedule edits record the staff member and exact before after values while unchanged saves add no history', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/exam/edit', auditExamInput($fixture))->assertSessionHasNoErrors();
    $log = AuditLog::where('subject_type', 'exam')->sole();
    expect($log->actor_id)->toBe($fixture['admin']->id);
    expect($log->actor_name)->toBe('Exam Administrator');
    expect($log->action)->toBe('updated');
    expect($log->before_values['start_date'])->toBe('2026-11-12');
    expect($log->after_values['start_date'])->toBe('2026-11-13');
    expect($log->before_values['end_date'])->toBe('2026-11-14');
    expect($log->after_values['end_date'])->toBe('2026-11-15');
    $this->post('/dashboard/exam/edit', auditExamInput($fixture))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('audit_logs', 1);
    expect(app(AuditTrail::class)->active())->toBeFalse();
});

test('manual result edits preserve score changes and searchable student exam and course context', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/exam/update=report', ['id' => $fixture['report']->id, 'first_ca' => 15])->assertOk();
    $log = AuditLog::sole();
    expect($log->subject_type)->toBe('result');
    expect($log->subject_label)->toContain('STD/25/003', 'ANA101', 'First semester');
    expect($log->before_values)->toBe(['first_ca' => 10]);
    expect($log->after_values)->toBe(['first_ca' => 15]);
    expect($fixture['report']->fresh()->first_ca)->toBe(15);
});

test('student and exam attempt resets record scope and counts but never attempt credentials', function (string $scope) {
    $fixture = auditFixture();
    $url = $scope === 'student' ? route('attempts.clearStudent', ['exam' => $fixture['exam']->uuid, 'student' => $fixture['student']->uuid]) : route('attempts.clearExam', $fixture['exam']->uuid);
    $this->actingAs($fixture['admin'])->post($url, ['confirmed' => true])->assertRedirect();
    $log = AuditLog::sole();
    expect($log->action)->toBe('attempts_cleared');
    expect($log->metadata)->toMatchArray(['scope' => $scope, 'attempts_removed' => 1, 'stored_results_preserved' => true]);
    expect($log->toJson())->not->toContain('private-attempt-token', hash('sha256', 'private-attempt-token'), 'access_token_hash');
    $this->assertModelMissing($fixture['attempt']);
    $this->assertModelExists($fixture['report']);
    if ($scope === 'student') {
        expect($log->subject_label)->toContain('STD/25/003');
    }
})->with(['student', 'exam']);

test('deleting an exam keeps its audit identity and actor even after both source records are gone', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/exam/delete-exam', ['id' => $fixture['exam']->id])->assertRedirect();
    $fixture['admin']->delete();
    $log = AuditLog::sole();
    expect($log->action)->toBe('deleted');
    expect($log->subject_label)->toContain('First semester');
    expect($log->actor_name)->toBe('Exam Administrator');
    expect($log->before_values['uuid'])->toBe($fixture['exam']->uuid);
    expect($log->after_values)->toBe([]);
    $this->assertModelMissing($fixture['exam']);
    $this->assertModelMissing($fixture['attempt']);
});

test('course deletion records its identity rather than bypassing audit events with a bulk query', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/course/delete', ['id' => $fixture['course']->id])->assertRedirect();
    $log = AuditLog::sole();
    expect($log->subject_type)->toBe('course');
    expect($log->subject_label)->toBe('ANA101 · Anatomy');
    expect($log->before_values['code'])->toBe('ANA101');
});

test('staff password changes are recorded without passwords hashes tokens or request bodies', function () {
    $fixture = auditFixture();
    $staff = User::factory()->create();
    $oldHash = $staff->password;
    $this->actingAs($fixture['admin'])->post('/dashboard/staff/'.$staff->id.'/update-password', [
        'password' => 'new-private-password', 'password_confirmation' => 'new-private-password', 'token' => 'request-secret',
    ])->assertRedirect();
    $log = AuditLog::sole();
    expect($log->metadata)->toBe(['password_changed' => true]);
    expect($log->before_values)->toBe([]);
    expect($log->after_values)->toBe([]);
    expect($log->toJson())->not->toContain('new-private-password', 'request-secret', $oldHash, $staff->fresh()->password);
});

test('record creation is audited and actor identity is preserved when administrators rename themselves', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/department/add', ['name' => 'New department'])->assertRedirect();
    $log = AuditLog::sole();
    expect($log->action)->toBe('created');
    expect($log->before_values)->toBe([]);
    expect($log->after_values['name'])->toBe('New department');
    $this->post('/dashboard/staff/'.$fixture['admin']->id.'/edit', ['name' => 'Renamed Administrator', 'email' => $fixture['admin']->email, 'status' => 'active', 'is_admin' => true])->assertRedirect();
    $renamed = AuditLog::latest('id')->first();
    expect($renamed->actor_name)->toBe('Exam Administrator');
    expect($renamed->after_values)->toBe(['name' => 'Renamed Administrator']);
});

test('validation failures and unauthorized requests do not create audit history', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/exam/edit', ['id' => $fixture['exam']->id])->assertSessionHasErrors();
    $this->actingAs(User::factory()->create(['status' => 'active', 'is_admin' => false]))->post('/dashboard/exam/delete-exam', ['id' => $fixture['exam']->id])->assertRedirect('/dashboard');
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertModelExists($fixture['exam']);
});

test('audit persistence failure rolls the business change back rather than silently losing its history', function () {
    $fixture = auditFixture();
    AuditLog::creating(function (): never {
        throw new RuntimeException('Audit storage unavailable');
    });
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($fixture['admin'])->post('/dashboard/exam/edit', auditExamInput($fixture));
        $this->fail('The write should fail when its audit record cannot be saved.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Audit storage unavailable');
    }
    expect($fixture['exam']->fresh()->start_date->toDateString())->toBe('2026-11-12');
    $this->assertDatabaseCount('audit_logs', 0);
    expect(app(AuditTrail::class)->active())->toBeFalse();
});

test('partial question creation failure rolls back both business data and earlier audit entries', function () {
    $fixture = auditFixture();
    QuestionOption::creating(function (QuestionOption $option): void {
        if ($option->option_text === 'Failure') {
            throw new RuntimeException('Option failed');
        }
    });
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($fixture['admin'])->post('/dashboard/exam/'.$fixture['paper']->uuid.'/add-question', [
            'single_exam_id' => $fixture['paper']->id, 'question_text' => 'New question', 'points' => 1,
            'options' => [['option_text' => 'Correct', 'is_correct' => true], ['option_text' => 'Failure', 'is_correct' => false]],
        ]);
        $this->fail('Question creation should fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Option failed');
    }
    $this->assertDatabaseCount('questions', 0);
    $this->assertDatabaseCount('question_options', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

test('real exam autosave and scheduled status updates do not create staff audit entries', function () {
    $fixture = auditFixture();
    $fixture['exam']->update(['start_date' => now()->subDays(3), 'end_date' => now()->subDay()]);
    $this->artisan('exams:update-statuses')->assertSuccessful();
    $this->assertDatabaseCount('audit_logs', 0);
    $this->withToken('private-attempt-token')->postJson('/api/save-answers', ['attempt_id' => $fixture['attempt']->id, 'answers' => []])->assertConflict();
    $this->assertDatabaseCount('audit_logs', 0);
});

test('audit history search filters dates and pagination include the full Lagos calendar day', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    for ($index = 0; $index < 21; $index++) {
        AuditLog::factory()->create(['actor_name' => 'Staff Ada', 'subject_label' => 'Semester % exam', 'subject_type' => 'exam', 'action' => 'updated', 'created_at' => '2026-11-13 23:59:59']);
    }
    AuditLog::factory()->create(['actor_name' => 'Other', 'subject_type' => 'student', 'created_at' => '2026-11-14 00:00:00']);
    $query = http_build_query(['search' => '%', 'type' => 'exam', 'action' => 'updated', 'from' => '2026-11-13', 'to' => '2026-11-13']);
    $this->actingAs($admin)->get('/dashboard/audit-history?'.$query)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Audit/Index')->where('logs.total', 21)->has('logs.data', 20)
        ->where('logs.next_page_url', fn (string $url) => str_contains($url, 'type=exam') && str_contains($url, 'search=%25')));
    $this->get('/dashboard/audit-history?'.$query.'&page=2')->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));
    $this->get('/dashboard/audit-history?search=staff%20ada')->assertInertia(fn (AssertableInertia $page) => $page->where('logs.total', 21));
    $this->get('/dashboard/audit-history?search=absent')->assertInertia(fn (AssertableInertia $page) => $page->where('logs.total', 0));
});

test('only active administrators can access history and filter validation rejects invalid input', function () {
    $this->get('/dashboard/audit-history')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['is_admin' => false, 'status' => 'active']))->get('/dashboard/audit-history')->assertRedirect('/dashboard');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'inactive']))->get('/dashboard/audit-history')->assertRedirect('/');
    $this->actingAs(User::factory()->create(['is_admin' => true, 'status' => 'active']));
    foreach ([['action' => 'invalid'], ['type' => 'invalid'], ['from' => 'not-a-date'], ['from' => '2026-11-14', 'to' => '2026-11-13']] as $input) {
        $this->getJson('/dashboard/audit-history?'.http_build_query($input))->assertUnprocessable();
    }
    $this->postJson('/dashboard/audit-history', [])->assertMethodNotAllowed();
});

test('audit entries cannot be edited or deleted through the audit model', function (string $operation) {
    $log = AuditLog::factory()->create();
    expect(fn () => $operation === 'edit' ? $log->update(['actor_name' => 'Altered']) : $log->delete())->toThrow(LogicException::class);
    $this->assertDatabaseCount('audit_logs', 1);
    expect($log->fresh()->actor_name)->not->toBe('Altered');
})->with(['edit', 'delete']);

test('an error response after a write rolls back the record and its audit entry', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    \Illuminate\Support\Facades\Route::post('/audit-failed-response', function () {
        Course::create(['uuid' => (string) str()->uuid(), 'code' => 'FAIL101', 'name' => 'Failed record']);

        return response()->json(['error' => 'The action failed.'], 422);
    })->middleware(['web', 'auth:web', \App\Http\Middleware\AuditChanges::class]);
    $this->actingAs($admin)->postJson('/audit-failed-response')->assertUnprocessable();
    $this->assertDatabaseCount('courses', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(app(AuditTrail::class)->active())->toBeFalse();
});

test('question creation and deletion retain the question identity and related option changes', function () {
    $fixture = auditFixture();
    $this->actingAs($fixture['admin'])->post('/dashboard/exam/'.$fixture['paper']->uuid.'/add-question', [
        'single_exam_id' => $fixture['paper']->id, 'question_text' => 'Question to audit', 'points' => 2,
        'options' => [['option_text' => 'Yes', 'is_correct' => true], ['option_text' => 'No', 'is_correct' => false]],
    ])->assertRedirect();
    $this->assertDatabaseCount('audit_logs', 3);
    $created = AuditLog::where('subject_type', 'question')->sole();
    expect($created->subject_label)->toContain('ANA101', 'First semester');
    expect($created->after_values['question_text'])->toBe('Question to audit');
    $question = Question::sole();
    $this->post('/dashboard/exam/delete-question', ['id' => $question->id])->assertRedirect();
    expect(AuditLog::where('action', 'deleted')->sole()->before_values['question_text'])->toBe('Question to audit');
    $this->assertModelMissing($question);
    $this->assertDatabaseCount('question_options', 0);
});
