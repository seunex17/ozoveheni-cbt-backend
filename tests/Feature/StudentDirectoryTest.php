<?php

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function directoryStudent(Department $department, array $attributes = []): Student
{
    return Student::forceCreate([
        'uuid' => str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'REG/'.str()->uuid(),
        'first_name' => 'Ada', 'middle_name' => 'Mary', 'last_name' => 'Okafor', 'gender' => 'F',
        'set' => '2026/2027', 'level' => 'nd', ...$attributes,
    ]);
}

function directoryDepartment(string $name = 'Community Health'): Department
{
    return Department::create(['name' => $name, 'uuid' => (string) str()->uuid()]);
}

test('student directory requires active administrator access', function () {
    $this->get('/dashboard/student')->assertRedirect('/login');
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/student')->assertRedirect('/dashboard');
    $inactive = User::factory()->create(['status' => 'inactive', 'is_admin' => true]);
    $this->actingAs($inactive)->get('/dashboard/student')->assertRedirect('/');
});

test('directory lists students immediately with bounded pagination and department names', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    for ($index = 0; $index < 25; $index++) {
        directoryStudent($department, ['first_name' => sprintf('Student %02d', $index)]);
    }
    $this->actingAs($admin)->get('/dashboard/student')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Student/Index')->has('students.data', 20)->where('students.total', 25)
        ->where('students.data.0.first_name', 'Student 00')->where('students.data.0.department.name', 'Community Health')
        ->missing('students.data.0.dob')->where('totalStudents', 25)->where('filters.search', ''));
});

test('name terms and department set and level filters compose without leaking other groups', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    $other = directoryDepartment('Medical Laboratory');
    $matching = directoryStudent($department, ['level' => 'ND']);
    directoryStudent($department, ['level' => 'hnd']);
    directoryStudent($department, ['set' => '2025/2026']);
    directoryStudent($other);
    directoryStudent($department, ['first_name' => 'Different', 'last_name' => 'Student']);
    $query = http_build_query(['search' => ' ada  OKAFOR ', 'department' => $department->uuid, 'set' => '2026/2027', 'level' => 'nd']);

    $this->actingAs($admin)->get('/dashboard/student?'.$query)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('students.data', 1)->where('students.data.0.id', $matching->id)->where('students.total', 1)
        ->where('filters.search', 'ada  OKAFOR')->where('filters.level', 'nd')->where('totalStudents', 5));
});

test('directory finds a registration number and middle name case insensitively', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    $student = directoryStudent($department, ['reg_no' => 'CH/2026/001', 'middle_name' => 'UniqueMiddle']);
    directoryStudent($department, ['middle_name' => 'Other']);
    $this->actingAs($admin)->get('/dashboard/student?search=ch%2F2026%2F001')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('students.data', 1)->where('students.data.0.id', $student->id));
    $this->get('/dashboard/student?search=uniquemiddle')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('students.data', 1)->where('students.data.0.id', $student->id));
});

test('search wildcard characters are literal and cannot expand the result set', function (string $term) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    $student = directoryStudent($department, ['first_name' => '100%_Ready!']);
    directoryStudent($department, ['first_name' => 'Another']);
    $this->actingAs($admin)->get('/dashboard/student?'.http_build_query(['search' => $term]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 1)->where('students.data.0.id', $student->id));
})->with(['%', '_', '!']);

test('pagination retains selected filters and sorts registration numbers deterministically', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    for ($index = 24; $index >= 0; $index--) {
        directoryStudent($department, ['reg_no' => sprintf('REG/%03d', $index)]);
    }
    $query = http_build_query(['department' => $department->uuid, 'set' => '2026/2027', 'level' => 'nd', 'sort' => 'registration']);
    $this->actingAs($admin)->get('/dashboard/student?'.$query)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('students.data.0.reg_no', 'REG/000')->where('students.total', 25)
        ->where('students.next_page_url', fn ($url) => str_contains($url, 'department='.$department->uuid) && str_contains($url, 'level=nd') && str_contains($url, 'sort=registration')));
    $this->get('/dashboard/student?'.$query.'&page=2')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('students.data', 5)->where('students.data.0.reg_no', 'REG/020'));
});

test('newest ordering and no result searches return usable directory responses', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    directoryStudent($department);
    $newest = directoryStudent($department, ['first_name' => 'Newest']);
    $this->actingAs($admin)->get('/dashboard/student?sort=newest')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('students.data.0.id', $newest->id));
    $this->get('/dashboard/student?search=not-present')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 0)->where('students.total', 0)->where('totalStudents', 2));
});

test('legacy group links redirect into the filtered directory with the requested level', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    $this->actingAs($admin)->get('/dashboard/student/department/'.$department->uuid.'/2026-2027/hnd')
        ->assertRedirect(route('student', ['department' => $department->uuid, 'set' => '2026/2027', 'level' => 'hnd']));
});

test('directory validates sort and query shape instead of accepting dynamic SQL identifiers', function (array $query) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($admin)->getJson('/dashboard/student?'.http_build_query($query))->assertUnprocessable();
})->with([
    'unsupported sort' => [['sort' => 'password']],
    'non UUID department' => [['department' => 'invalid']],
    'overlong search' => [['search' => str_repeat('a', 101)]],
    'array search' => [['search' => ['x']]],
]);

test('search partial reload retains a bounded result without resending filter options', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = directoryDepartment();
    directoryStudent($department);

    $this->actingAs($admin)->get('/dashboard/student?search=Ada', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(\Illuminate\Http\Request::create('/dashboard/student')) ?? '',
        'X-Inertia-Partial-Component' => 'Student/Index',
        'X-Inertia-Partial-Data' => 'students,filters,totalStudents',
    ])->assertOk()->assertJsonPath('props.students.total', 1)
        ->assertJsonPath('props.filters.search', 'Ada')
        ->assertJsonMissingPath('props.departments')
        ->assertJsonMissingPath('props.sets')
        ->assertJsonMissingPath('props.levels');
});
