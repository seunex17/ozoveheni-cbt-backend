<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('staff management requires active administrators', function () {
    $this->get('/dashboard/staff')->assertRedirect('/login');
    $staff = User::factory()->create(['is_admin' => false, 'status' => 'active']);
    $this->actingAs($staff)->get('/dashboard/staff')->assertRedirect('/dashboard');
    foreach (['/dashboard/staff/add', '/dashboard/staff/'.$staff->id.'/edit', '/dashboard/staff/'.$staff->id.'/update-password'] as $route) {
        $this->post($route, ['name' => 'Blocked'])->assertRedirect('/dashboard');
    }
    $inactive = User::factory()->create(['is_admin' => true, 'status' => 'inactive']);
    $this->actingAs($inactive)->get('/dashboard/staff')->assertRedirect('/');
    $this->assertDatabaseMissing('users', ['name' => 'Blocked']);
});

test('staff directory paginates and excludes sensitive fields', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    User::factory()->count(24)->create();
    $this->actingAs($admin)->get('/dashboard/staff')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Staff/Manage')->has('users.data', 20)->where('users.total', 25)->where('totalStaff', 25)
        ->missing('users.data.0.password')->missing('users.data.0.remember_token')->missing('users.data.0.email_verified_at'));
});

test('staff search composes with role and status without leaking other accounts', function () {
    $admin = User::factory()->create(['name' => 'Administrator', 'is_admin' => true, 'status' => 'active']);
    $match = User::factory()->create(['name' => 'Ada Staff', 'email' => 'ada@example.com', 'status' => 'active', 'is_admin' => false]);
    User::factory()->create(['name' => 'Ada Admin', 'status' => 'active', 'is_admin' => true]);
    User::factory()->create(['name' => 'Ada Inactive', 'status' => 'inactive', 'is_admin' => false]);
    $this->actingAs($admin)->get('/dashboard/staff?search=ADA&role=staff&status=active')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 1)->where('users.data.0.id', $match->id));
    $this->get('/dashboard/staff?search=ADA%40EXAMPLE.COM')->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 1)->where('users.data.0.id', $match->id));
    $this->get('/dashboard/staff?role=admin&status=active')->assertInertia(fn (AssertableInertia $page) => $page->where('users.total', 2));
    $this->get('/dashboard/staff?status=inactive')->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Ada Inactive'));
    $this->get('/dashboard/staff?search=NoMatch')->assertInertia(fn (AssertableInertia $page) => $page->where('users.total', 0)->where('totalStaff', 4));
});

test('staff pagination preserves filters and supports newest sorting', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active', 'created_at' => now()->subDays(2)]);
    User::factory()->count(21)->create(['status' => 'active', 'is_admin' => false, 'created_at' => now()->subDay()]);
    $new = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($admin)->get('/dashboard/staff?role=staff&status=active&sort=newest')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 20)->where('users.data.0.id', $new->id)
        ->where('users.next_page_url', fn ($url) => str_contains($url, 'role=staff') && str_contains($url, 'status=active') && str_contains($url, 'sort=newest')));
    $this->get('/dashboard/staff?role=staff&status=active&sort=newest&page=2')->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 2));
});

test('staff search treats wildcard characters literally', function (string $term) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active', 'name' => 'Admin', 'email' => 'admin@example.com']);
    User::factory()->create(['name' => 'Name '.$term, 'email' => 'person@example.com']);
    $this->actingAs($admin)->get('/dashboard/staff?'.http_build_query(['search' => $term]))->assertInertia(fn (AssertableInertia $page) => $page->where('users.total', 1));
})->with(['%', '_', '!']);

test('creating staff hashes the password and refreshes the directory with the account first', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active', 'created_at' => now()->subDay()]);
    $response = $this->actingAs($admin)->post('/dashboard/staff/add', [
        'name' => 'New Staff', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'is_admin' => '0', 'status' => 'active', 'remember_token' => 'injected',
    ])->assertRedirectToRoute('staff', ['sort' => 'newest']);
    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect(Hash::check('password123', $user->password))->toBeTrue();
    expect($user->remember_token)->toBeNull();
    $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('users.data.0.id', $user->id)->where('users.current_page', 1));
});

test('staff creation returns field errors without creating accounts', function (array $input, string $field) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active', 'name' => 'Existing', 'email' => 'existing@example.com']);
    $this->actingAs($admin)->from('/dashboard/staff')->post('/dashboard/staff/add', [
        ...['name' => 'New Staff', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => '0'], ...$input,
    ])->assertRedirect('/dashboard/staff')->assertSessionHasErrors($field);
    $this->assertDatabaseCount('users', 1);
})->with([
    [['name' => ''], 'name'], [['name' => 'Existing'], 'name'], [['name' => str_repeat('a', 256)], 'name'],
    [['email' => 'invalid'], 'email'], [['email' => 'existing@example.com'], 'email'], [['password' => 'short'], 'password'],
    [['password_confirmation' => 'different'], 'password'], [['is_admin' => 'admin'], 'is_admin'], [['status' => 'invalid'], 'status'],
]);

test('editing preserves unchanged unique values and only changes validated account fields', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $target = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $originalPassword = $target->password;
    $this->actingAs($admin)->post('/dashboard/staff/'.$target->id.'/edit', [
        'name' => $target->name, 'email' => $target->email, 'is_admin' => '1', 'status' => 'inactive', 'password' => 'injected',
    ])->assertRedirectToRoute('staff', ['search' => $target->email]);
    $target->refresh();
    expect($target->status)->toBe('inactive');
    expect((bool) $target->is_admin)->toBeTrue();
    expect($target->password)->toBe($originalPassword);
    $this->post('/dashboard/staff/'.$target->id.'/edit', ['name' => $target->name, 'email' => $target->email, 'is_admin' => '0', 'status' => 'active'])->assertRedirect();
    expect((bool) $target->fresh()->is_admin)->toBeFalse();
    $this->post('/dashboard/staff/'.$target->id.'/edit', ['name' => $admin->name, 'email' => $admin->email, 'is_admin' => 'invalid', 'status' => 'invalid'])
        ->assertSessionHasErrors(['name', 'email', 'is_admin', 'status']);
});

test('password changes require confirmation and only change the password', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $target = User::factory()->create(['is_admin' => false, 'status' => 'active']);
    $this->actingAs($admin)->post('/dashboard/staff/'.$target->id.'/update-password', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('/dashboard/staff/'.$target->id.'/update-password', ['password' => 'newpassword123', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
    $this->post('/dashboard/staff/'.$target->id.'/update-password', ['password' => 'newpassword123', 'password_confirmation' => 'newpassword123', 'is_admin' => true])
        ->assertRedirectToRoute('staff', ['search' => $target->email]);
    $target->refresh();
    expect(Hash::check('newpassword123', $target->password))->toBeTrue();
    expect((bool) $target->is_admin)->toBeFalse();
});

test('staff directory rejects invalid filters', function (array $input, string $field) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->getJson('/dashboard/staff?'.http_build_query($input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([[['search' => ['invalid']], 'search'], [['search' => str_repeat('a', 101)], 'search'], [['role' => 'invalid'], 'role'], [['status' => 'invalid'], 'status'], [['sort' => 'invalid'], 'sort']]);
