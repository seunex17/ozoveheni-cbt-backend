<?php

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

test('web requests still require a valid csrf token when no trusted origin is present', function () {
    // Laravel normally bypasses CSRF checks in tests; exercise the real middleware.
    $this->app->instance('env', 'local');
    config(['debugbar.enabled' => false]);
    Route::middleware(['web', config('sanctum.middleware.validate_csrf_token')])->post('/upgrade-csrf-check', fn (Request $request) => response()->json([
        'value' => $request->session()->get('existing_value'),
    ]));

    $this->withSession(['_token' => 'valid-token', 'existing_value' => 'preserved'])
        ->postJson('/upgrade-csrf-check')->assertStatus(419);

    $this->withSession(['_token' => 'valid-token', 'existing_value' => 'preserved'])
        ->postJson('/upgrade-csrf-check', [], ['X-CSRF-TOKEN' => 'valid-token'])
        ->assertOk()->assertJson(['value' => 'preserved']);
});

test('sessions written before the upgrade remain readable', function () {
    $handler = new ArraySessionHandler(120);
    $session = new Store('upgrade-session', $handler, null, config('session.serialization'));
    $handler->write($session->getId(), serialize(['staff_id' => 42, '_token' => 'existing-token']));

    $session->start();

    expect($session->get('staff_id'))->toBe(42)
        ->and($session->token())->toBe('existing-token');
});

test('file cache still restores array payloads with object unserialization disabled', function () {
    $cache = Cache::store('file');
    $key = 'laravel-upgrade-'.str()->uuid();

    try {
        $cache->put($key, ['count' => 150, 'status' => 'ready'], 60);
        expect($cache->get($key))->toBe(['count' => 150, 'status' => 'ready']);
    } finally {
        $cache->forget($key);
    }
});
