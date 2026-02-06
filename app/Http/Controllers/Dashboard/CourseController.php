<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: CourseController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/6/26
 * Time: 7:47 AM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::orderBy('name')
            ->paginate();

        return Inertia::render('Dashboard/Course/Index', [
            'courses' => Inertia::scroll(fn () => $courses),
        ]);
    }

    public function add()
    {
        return Inertia::render('Dashboard/Course/Add');
    }

    public function addPost(Request $request)
    {
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'name' => 'required',
            'code' => 'required|unique:courses',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $input['uuid'] = Str::uuid();
        Course::create($input);

        return redirect()
            ->route('course')
            ->with('success', 'Course added successfully.');
    }

    public function edit(string $uuid)
    {
        $course = Course::where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Dashboard/Course/Edit', [
            'course' => $course,
        ]);
    }

    public function editPost(Request $request, string $uuid)
    {
        $course = Course::where('uuid', $uuid)->firstOrFail();
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'name' => 'required',
            'code' => 'required|unique:courses,code,'.$course->id,
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $course->update($input);

        return redirect()
            ->route('course')
            ->with('success', 'Course updated successfully.');
    }

    public function delete(Request $request)
    {
        Course::where('id', $request->id)->delete();

        return back()
            ->with('success', 'Course deleted successfully.');
    }
}
