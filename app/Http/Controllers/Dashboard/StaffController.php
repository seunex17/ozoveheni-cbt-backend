<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: StaffController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/4/26
 * Time: 10:09 PM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'role' => ['nullable', 'in:admin,staff'],
            'sort' => ['nullable', 'in:name,newest'],
        ]);
        $filters = ['search' => trim($input['search'] ?? ''), 'status' => $input['status'] ?? '', 'role' => $input['role'] ?? '', 'sort' => $input['sort'] ?? 'name'];
        $users = User::query()->select(['id', 'name', 'email', 'status', 'is_admin', 'created_at']);
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filters['search'])).'%';
            $users->where(fn (Builder $query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term]));
        }
        if ($filters['status'] !== '') {
            $users->where('status', $filters['status']);
        }
        if ($filters['role'] !== '') {
            $users->where('is_admin', $filters['role'] === 'admin');
        }
        if ($filters['sort'] === 'newest') {
            $users->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $users->orderBy('name')->orderBy('id');
        }

        return Inertia::render('Dashboard/Staff/Manage', [
            'users' => $users->paginate(20)->withQueryString(), 'filters' => $filters, 'totalStaff' => User::count(),
        ]);
    }

    public function updatePassword(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        return Inertia::render('Dashboard/Staff/UpdatePassword', [
            'user' => $user,
        ]);
    }

    public function updatePasswordPost(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($id);
        $input = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user->update(['password' => $input['password']]);

        return to_route('staff', ['search' => $user->email])->with('success', 'Password updated successfully.');
    }

    public function editPost(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($id);
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user)],
            'status' => ['required', 'in:active,inactive'],
            'is_admin' => ['required', 'boolean'],
        ]);
        $user->update($input);

        return to_route('staff', ['search' => $user->email])->with('success', 'Account updated successfully.');
    }

    public function edit(string $id)
    {
        $user = User::findOrFail($id);

        return Inertia::render('Dashboard/Staff/Edit', [
            'user' => $user,
        ]);
    }

    public function add()
    {
        return Inertia::render('Dashboard/Staff/Add');
    }

    public function addPost(Request $request): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);
        User::create($input);

        return to_route('staff', ['sort' => 'newest'])->with('success', 'Account added successfully.');
    }
}
