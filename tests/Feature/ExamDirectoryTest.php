<?php

use App\Models\Department;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function examDirectoryDepartment(string $name = 'Community Health'): Department
{
    return Department::create(['uuid' => (string) str()->uuid(), 'name' => $name]);
}

function directoryExam(Department $department, array $input = []): Exam
{
    return Exam::forceCreate([
        'uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'First semester exam',
        'subtitle' => '2026/2027', 'set' => '2025/2026', 'level' => 'nd1', 'status' => 'pending',
        'start_date' => '2026-11-10', 'end_date' => '2026-11-20', ...$input,
    ]);
}

function examDirectoryInput(Department $department): array
{
    return ['department_id' => $department->id, 'title' => 'New examination', 'subtitle' => '2026/2027', 'set' => '2025/2026', 'level' => 'nd1', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20'];
}

test('exam directory requires active administrator access', function () {
    $this->get('/dashboard/exam')->assertRedirect('/login');
    $staff = User::factory()->create(['is_admin' => false, 'status' => 'active']);
    $this->actingAs($staff)->get('/dashboard/exam')->assertRedirect('/dashboard');
    $this->post('/dashboard/exam/add', [])->assertRedirect('/dashboard');
    $this->post('/dashboard/exam/edit', [])->assertRedirect('/dashboard');
    $inactive = User::factory()->create(['is_admin' => true, 'status' => 'inactive']);
    $this->actingAs($inactive)->get('/dashboard/exam')->assertRedirect('/');
});

test('exam directory paginates with department names and filter options', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    for ($i = 0; $i < 24; $i++) {
        directoryExam($department);
    }
    $this->actingAs($admin)->get('/dashboard/exam')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Exams/Index')->has('exams.data', 20)->where('exams.total', 24)->where('totalExams', 24)
        ->where('exams.data.0.department.name', 'Community Health')->where('sets.0', '2025/2026')->where('levels.0', 'nd1')->has('departments', 1));
});

test('exam filters compose without leaking other departments sets levels or statuses', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    $other = examDirectoryDepartment('Other Department');
    $match = directoryExam($department, ['level' => 'ND1']);
    directoryExam($other);
    directoryExam($department, ['level' => 'nd2']);
    directoryExam($department, ['set' => '2024/2025']);
    directoryExam($department, ['status' => 'active']);
    directoryExam($department, ['title' => 'Second semester']);
    $query = http_build_query(['search' => 'FIRST', 'department' => $department->uuid, 'set' => '2025/2026', 'level' => 'nd1', 'status' => 'pending']);
    $this->actingAs($admin)->get('/dashboard/exam?'.$query)->assertInertia(fn (AssertableInertia $page) => $page
        ->has('exams.data', 1)->where('exams.data.0.id', $match->id)->where('totalExams', 6));
    $this->get('/dashboard/exam?search=2026%2F2027')->assertInertia(fn (AssertableInertia $page) => $page->where('exams.total', 6));
    $this->get('/dashboard/exam?search=NoMatch')->assertInertia(fn (AssertableInertia $page) => $page->where('exams.total', 0));
});

test('exam searches treat wildcard characters literally', function (string $term) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    directoryExam($department, ['title' => 'Exam '.$term]);
    directoryExam($department);
    $this->actingAs($admin)->get('/dashboard/exam?'.http_build_query(['search' => $term]))->assertInertia(fn (AssertableInertia $page) => $page->where('exams.total', 1));
})->with(['%', '_', '!']);

test('exam sorting and pagination preserve selected filters', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    for ($i = 0; $i < 21; $i++) {
        directoryExam($department, ['title' => sprintf('Exam %02d', $i), 'created_at' => now()->subDay()]);
    }
    directoryExam($department, ['title' => 'Newest', 'start_date' => '2026-12-01', 'end_date' => '2026-12-20']);
    $this->actingAs($admin)->get('/dashboard/exam?sort=title&status=pending')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('exams.data.0.title', 'Exam 00')->where('exams.next_page_url', fn ($url) => str_contains($url, 'sort=title') && str_contains($url, 'status=pending')));
    $this->get('/dashboard/exam?sort=title&status=pending&page=2')->assertInertia(fn (AssertableInertia $page) => $page->has('exams.data', 2));
    foreach (['newest', 'start_date'] as $sort) {
        $this->get('/dashboard/exam?sort='.$sort)->assertInertia(fn (AssertableInertia $page) => $page->where('exams.data.0.title', 'Newest'));
    }
});

test('creating an exam refreshes the list with the new group first', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    directoryExam($department, ['created_at' => now()->subDay()]);
    $response = $this->actingAs($admin)->from('/dashboard/exam?search=Missing&page=3')->post('/dashboard/exam/add', [...examDirectoryInput($department), 'status' => 'completed', 'uuid' => 'untrusted'])
        ->assertRedirectToRoute('exam');
    $this->assertDatabaseHas('exams', ['title' => 'New examination', 'status' => 'pending', 'level' => 'nd1']);
    $this->assertDatabaseMissing('exams', ['uuid' => 'untrusted']);
    $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('exams.data.0.title', 'New examination')->where('exams.current_page', 1)->where('filters.search', ''));
});

test('exam form returns field errors without creating records', function (array $input, string $field) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    $this->actingAs($admin)->from('/dashboard/exam')->post('/dashboard/exam/add', [...examDirectoryInput($department), ...$input])->assertRedirect('/dashboard/exam')->assertSessionHasErrors($field);
    $this->assertDatabaseCount('exams', 0);
})->with([
    [['department_id' => 999], 'department_id'], [['title' => ''], 'title'], [['title' => str_repeat('a', 256)], 'title'],
    [['subtitle' => ['invalid']], 'subtitle'], [['set' => ''], 'set'], [['level' => ''], 'level'],
    [['start_date' => 'invalid'], 'start_date'], [['end_date' => '2026-11-09'], 'end_date'], [['end_date' => '2026-11-10'], 'end_date'],
]);

test('exam edits update group details without changing lifecycle status or uuid', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = examDirectoryDepartment();
    $exam = directoryExam($department, ['status' => 'active']);
    $this->actingAs($admin)->post('/dashboard/exam/edit', ['id' => $exam->id, ...examDirectoryInput($department), 'level' => 'nd2', 'uuid' => 'untrusted', 'status' => 'completed'])
        ->assertRedirectToRoute('exam', ['search' => 'New examination']);
    $this->assertDatabaseHas('exams', ['id' => $exam->id, 'title' => 'New examination', 'level' => 'nd2', 'status' => 'active', 'uuid' => $exam->uuid]);
    $this->post('/dashboard/exam/edit', ['id' => $exam->id, ...examDirectoryInput($department), 'end_date' => '2026-11-09'])->assertSessionHasErrors('end_date');
    expect($exam->fresh()->end_date->toDateString())->toBe('2026-11-20');
});

test('exam directory rejects invalid query inputs', function (array $input, string $field) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->getJson('/dashboard/exam?'.http_build_query($input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([[['department' => 'invalid'], 'department'], [['status' => 'invalid'], 'status'], [['sort' => 'invalid'], 'sort'], [['search' => ['invalid']], 'search'], [['search' => str_repeat('a', 101)], 'search']]);
