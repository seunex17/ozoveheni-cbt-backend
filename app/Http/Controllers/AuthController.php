<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: AuthController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/4/26
 * Time: 6:40 PM
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function index()
    {
        return Inertia::render('Index');
    }

    public function login(Request $request)
    {
        $credentials = Validator::make($request->all(), [
            'name' => ['required'],
            'password' => ['required'],
        ]);

        if ($credentials->fails()) {
            return back()->with('error', $credentials->errors()->first());
        }

        if (Auth::attempt($request->only('name', 'password'))) {
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()
            ->with('error', 'Invalid username or password.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        return redirect()->route('home')
            ->with('success', 'Logged out successfully.');
    }
}
