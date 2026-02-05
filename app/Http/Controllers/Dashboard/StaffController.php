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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function index()
    {
        $users = User::latest('created_at')->paginate();

        return Inertia::render('Dashboard/Staff/Manage', [
            'users' => Inertia::scroll(fn () => $users),
        ]);
    }

    public function updatePassword(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        return Inertia::render('Dashboard/Staff/UpdatePassword', [
            'user' => $user,
        ]);
    }

    public function updatePasswordPost(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $user->password = bcrypt($input['password']);
        $user->save();

        return redirect()->route('staff')
            ->with('success', 'Password updated successfully.');
    }

    public function editPost(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validate = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'name' => ['required', 'string', 'max:255', 'unique:users,name,'.$user->id],
            'status' => ['required', 'string'],
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $user->update($request->input());

        return redirect()->route('staff')
            ->with('success', 'Account updated successfully.');
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

    public function addPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        User::create($request->input());

        return redirect()->route('staff')
            ->with('success', 'Account added successfully.');
    }
}
