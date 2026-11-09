<?php

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function departmentRecord(string $name, array $attributes = []): Department
{
    return Department::forceCreate(['name' => $name, 'uuid' => (string) str()->uuid(), ...$attributes]);
}

function departmentStudent(Department $department): Student
{
    return Student::forceCreate([
        'uuid' => str()->uuid(), 'reg_no' => 'TEST/'.str()->uuid(), 'department_id' => $department->id,
        'first_name' => 'Ada', 'last_name' => 'Okafor', 'gender' => 'F', 'set' => '2026/2027', 'level' => 'nd',
    ]);
}

test('department directory requires active administrator access', function () {
    $this->get('/dashboard/department')->assertRedirect('/login');
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/department')->assertRedirect('/dashboard');
    $inactive = User::factory()->create(['status' => 'inactive', 'is_admin' => true]);
    $this->actingAs($inactive)->get('/dashboard/department')->assertRedirect('/');
});

test('department directory paginates without exposing historical enrollment counts', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $department = departmentRecord('A Community Health');
    departmentStudent($department);
    departmentStudent($department);
    for ($index = 0; $index < 24; $index++) {
        departmentRecord(sprintf('Department %02d', $index));
    }
    $this->actingAs($admin)->get('/dashboard/department')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Department/Index')->has('departments.data', 20)->where('departments.total', 25)
        ->where('departments.data.0.name', 'A Community Health')->missing('departments.data.0.students_count')
        ->missing('departments.data.0.exams_count')->missing('departments.data.0.students')->where('totalDepartments', 25));
});

test('department search preserves search and sort across pages', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    for ($index = 0; $index < 21; $index++) {
        departmentStudent(departmentRecord(sprintf('Health %02d', $index)));
    }
    departmentRecord('Health without students');
    departmentStudent(departmentRecord('Science'));
    $this->actingAs($admin)->get('/dashboard/department?search=HEALTH&sort=name')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('departments.total', 22)->has('departments.data', 20)
        ->where('departments.next_page_url', fn ($url) => str_contains($url, 'search=HEALTH') && str_contains($url, 'sort=name')));
    $this->get('/dashboard/department?search=HEALTH&sort=name&page=2')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('departments.data', 2)->where('departments.data.0.name', 'Health 20'));
    $this->get('/dashboard/department?search=health%20without')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('departments.data', 1)->where('departments.data.0.name', 'Health without students')->where('totalDepartments', 23));
});

test('department name search treats wildcard characters as literal text', function (string $term) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    departmentRecord('Health '.$term.' Studies');
    departmentRecord('Health Science');
    $this->actingAs($admin)->get('/dashboard/department?'.http_build_query(['search' => $term]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('departments.total', 1));
})->with(['%', '_', '!']);

test('department sorting supports alphabetical names and recent additions', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $oldest = departmentRecord('A Health', ['created_at' => now()->subDays(3)]);
    $popular = departmentRecord('B Science', ['created_at' => now()->subDays(2)]);
    departmentStudent($oldest);
    departmentStudent($popular);
    departmentStudent($popular);
    departmentRecord('C New department');
    $this->actingAs($admin)->get('/dashboard/department?sort=name')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('departments.data.0.name', 'A Health')->where('departments.data.1.name', 'B Science')->where('departments.data.2.name', 'C New department'));
    $this->get('/dashboard/department?sort=newest')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('departments.data.0.name', 'C New department')->where('departments.data.2.name', 'A Health'));
    $this->get('/dashboard/department?search=NoMatch')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('departments.data', 0)->where('departments.total', 0)->where('totalDepartments', 3));
});

test('department directory rejects invalid filters', function (array $query, string $field) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($admin)->getJson('/dashboard/department?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['search' => str_repeat('a', 101)], 'search'],
    [['search' => ['unexpected']], 'search'],
    [['sort' => 'name desc'], 'sort'],
    [['sort' => 'students'], 'sort'],
]);

test('creating a department refreshes the directory with the new record first', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    departmentRecord('Existing department', ['created_at' => now()->subDay()]);

    $response = $this->actingAs($admin)->from('/dashboard/department?search=Unrelated&page=3')
        ->post('/dashboard/department/add', ['name' => 'New Health Department']);
    $response->assertRedirectToRoute('department', ['sort' => 'newest'])->assertSessionHas('success');
    $this->assertDatabaseHas('departments', ['name' => 'New Health Department']);
    $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('departments.data.0.name', 'New Health Department')->where('totalDepartments', 2)
        ->where('filters.search', '')->where('filters.sort', 'newest')->where('departments.current_page', 1));
});

test('invalid department names return field errors without creating records', function (mixed $name) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($admin)->from('/dashboard/department')->post('/dashboard/department/add', ['name' => $name])
        ->assertRedirect('/dashboard/department')->assertSessionHasErrors('name');
    $this->assertDatabaseCount('departments', 0);
})->with([null, '', '   ', str_repeat('a', 256), [['unexpected']]]);

test('staff cannot create departments', function () {
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->post('/dashboard/department/add', ['name' => 'Not allowed'])->assertRedirect('/dashboard');
    $this->assertDatabaseCount('departments', 0);
});
