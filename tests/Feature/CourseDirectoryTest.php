<?php

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function directoryCourse(array $attributes = []): Course
{
    return Course::forceCreate(['uuid' => (string) str()->uuid(), 'name' => 'Human Anatomy', 'code' => 'ANA101', 'unit' => 3, ...$attributes]);
}

test('course management requires active administrator access', function () {
    $this->get('/dashboard/course')->assertRedirect('/login');
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/course')->assertRedirect('/dashboard');
    $this->post('/dashboard/course/add', ['name' => 'Blocked', 'code' => 'BLOCK'])->assertRedirect('/dashboard');
    $inactive = User::factory()->create(['status' => 'inactive', 'is_admin' => true]);
    $this->actingAs($inactive)->get('/dashboard/course')->assertRedirect('/');
    $this->assertDatabaseCount('courses', 0);
});

test('course directory paginates and preserves name or code search and sort', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    for ($i = 0; $i < 24; $i++) {
        directoryCourse(['name' => sprintf('Anatomy %02d', $i), 'code' => sprintf('ANA%03d', $i)]);
    }
    directoryCourse(['name' => 'English Language', 'code' => 'ENG101']);
    $this->actingAs($admin)->get('/dashboard/course')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Course/Index')->has('courses.data', 20)->where('courses.total', 25)->where('totalCourses', 25));
    $this->get('/dashboard/course?search=ANATOMY&sort=code')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('courses.total', 24)->where('courses.data.0.code', 'ANA000')
        ->where('courses.next_page_url', fn ($url) => str_contains($url, 'search=ANATOMY') && str_contains($url, 'sort=code')));
    $this->get('/dashboard/course?search=ANATOMY&sort=code&page=2')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('courses.data', 4));
    $this->get('/dashboard/course?search=eng101')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('courses.data', 1)->where('courses.data.0.name', 'English Language'));
    $this->get('/dashboard/course?search=Missing')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('courses.total', 0));
});

test('course search treats wildcard characters literally', function (string $term) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    directoryCourse(['name' => 'Special '.$term.' course']);
    directoryCourse(['name' => 'Other course', 'code' => 'OTHER']);
    $this->actingAs($admin)->get('/dashboard/course?'.http_build_query(['search' => $term]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('courses.total', 1));
})->with(['%', '_', '!']);

test('creating a course validates fields and refreshes the list with the new course first', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    directoryCourse(['created_at' => now()->subDay()]);
    $response = $this->actingAs($admin)->from('/dashboard/course?search=Missing&page=3')->post('/dashboard/course/add', [
        'name' => 'English Language', 'code' => 'ENG101', 'unit' => 2, 'uuid' => 'untrusted',
    ])->assertRedirectToRoute('course', ['sort' => 'newest']);
    $this->assertDatabaseHas('courses', ['name' => 'English Language', 'code' => 'ENG101', 'unit' => 2]);
    $this->assertDatabaseMissing('courses', ['uuid' => 'untrusted']);
    $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('courses.data.0.code', 'ENG101')->where('courses.current_page', 1)->where('filters.search', '')->where('totalCourses', 2));
});

test('course validation returns field errors without saving', function (array $input, string $field) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    directoryCourse();
    $this->actingAs($admin)->from('/dashboard/course')->post('/dashboard/course/add', [...['name' => 'New Course', 'code' => 'NEW101', 'unit' => 3], ...$input])
        ->assertRedirect('/dashboard/course')->assertSessionHasErrors($field);
    $this->assertDatabaseCount('courses', 1);
})->with([
    [['name' => ''], 'name'], [['name' => str_repeat('a', 256)], 'name'], [['name' => ['invalid']], 'name'],
    [['code' => ''], 'code'], [['code' => 'ANA101'], 'code'], [['code' => str_repeat('A', 11)], 'code'], [['code' => ['invalid']], 'code'],
    [['unit' => -1], 'unit'], [['unit' => 1.5], 'unit'], [['unit' => 2147483648], 'unit'],
]);

test('course units may be omitted or zero', function (array $input, mixed $expected) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($admin)->post('/dashboard/course/add', ['name' => 'Course', 'code' => 'NEW101', ...$input])->assertRedirectToRoute('course', ['sort' => 'newest']);
    $this->assertDatabaseHas('courses', ['code' => 'NEW101', 'unit' => $expected]);
})->with([[[], 0], [['unit' => null], null], [['unit' => 0], 0]]);

test('editing a course accepts its unchanged code and rejects another course code', function () {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $course = directoryCourse();
    directoryCourse(['name' => 'English', 'code' => 'ENG101']);
    $this->actingAs($admin)->post('/dashboard/course/'.$course->uuid.'/edit', ['name' => 'Updated Anatomy', 'code' => 'ANA101', 'unit' => 4, 'uuid' => 'untrusted'])
        ->assertRedirectToRoute('course', ['search' => 'ANA101']);
    $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Updated Anatomy', 'code' => 'ANA101', 'unit' => 4, 'uuid' => $course->uuid]);
    $this->from('/dashboard/course')->post('/dashboard/course/'.$course->uuid.'/edit', ['name' => 'Rejected', 'code' => 'ENG101', 'unit' => 4])
        ->assertSessionHasErrors('code');
    $this->assertDatabaseMissing('courses', ['name' => 'Rejected']);
});

test('course directory rejects invalid query input', function (array $query, string $field) {
    $admin = User::factory()->create(['status' => 'active', 'is_admin' => true]);
    $this->actingAs($admin)->getJson('/dashboard/course?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([[['sort' => 'invalid'], 'sort'], [['search' => str_repeat('a', 101)], 'search'], [['search' => ['invalid']], 'search']]);
