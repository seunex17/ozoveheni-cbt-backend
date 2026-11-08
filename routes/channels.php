<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('exam-hall.monitor', function (\App\Models\User $user): bool {
    return $user->status === 'active';
}, ['guards' => ['web']]);
