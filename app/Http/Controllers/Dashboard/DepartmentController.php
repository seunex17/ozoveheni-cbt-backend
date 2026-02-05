<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: DepartmentController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/5/26
 * Time: 12:48 PM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')
            ->paginate();

        return Inertia::render('Dashboard/Department/Index', [
            'departments' => Inertia::scroll(fn () => $departments),
        ]);
    }

    public function add()
    {
        return Inertia::render('Dashboard/Department/Add');
    }

    public function addPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        Department::create([
            'name' => $request->name,
            'uuid' => Str::uuid(),
        ]);

        return redirect()->route('department')
            ->with('success', 'Department created successfully.');
    }

    public function edit($uuid)
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Dashboard/Department/Edit', [
            'department' => $department,
        ]);
    }

    public function editPost(Request $request, $uuid)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $department = Department::where('uuid', $uuid)->firstOrFail();
        $department->update($request->input());

        return redirect()->route('department')
            ->with('success', 'Department updated successfully.');
    }

    public function delete(Request $request)
    {
        $department = Department::findOrFail($request->input('id'));
        $department->delete();

        return back()->with('success', 'Department deleted successfully.');
    }
}
