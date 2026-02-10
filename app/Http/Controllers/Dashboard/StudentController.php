<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: StudentController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/5/26
 * Time: 7:46 PM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Student/Index', [
            'departments' => $departments,
        ]);
    }

    public function department(string $uuid, string $set, string $level)
    {
        $set = Str::replace('-', '/', $set);
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $students = Student::where('department_id', $department->id)
            ->where('set', $set)
            ->orderBy('first_name')
            ->paginate();

        return Inertia::render('Student/Department', [
            'department' => $department,
            'students' => Inertia::scroll(fn () => $students),
            'level' => $level,
        ]);
    }

    public function add(string $uuid, string $set, $level)
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $set = Str::replace('-', '/', $set);

        return Inertia::render('Student/Add', [
            'department' => $department,
            'set' => $set,
            'level' => $level,
        ]);
    }

    public function addPost(Request $request)
    {
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'first_name' => 'required',
            'middle_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'reg_no' => 'required|unique:students,reg_no',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('errors', $validate->errors()->first());
        }

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo')->store('students', 'public');
            $input['photo'] = $photo;
        }
        $input['uuid'] = Str::uuid();

        Student::create($input);

        return redirect()
            ->back()
            ->with('success', 'Student added successfully.');
    }

    public function edit(string $uuid)
    {
        $student = Student::where('uuid', $uuid)->firstOrFail();
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Student/Edit', [
            'student' => $student,
            'departments' => $departments,
        ]);
    }

    public function editPost(Request $request)
    {
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'first_name' => 'required',
            'middle_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('errors', $validate->errors()->first());
        }

        $student = Student::where('uuid', $input['uuid'])->firstOrFail();

        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }

            $photo = $request->file('photo')->store('students', 'public');
            $input['photo'] = $photo;
        }

        $student->update($input);

        return redirect()
            ->back()
            ->with('success', 'Student added successfully.');
    }

    public function delete(Request $request)
    {
        $student = Student::findOrFail($request->id);

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        $student->delete();

        return redirect()
            ->back()
            ->with('success', 'Student deleted successfully.');
    }
}
