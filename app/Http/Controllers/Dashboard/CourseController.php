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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,code,newest'],
        ]);
        $filters = ['search' => trim($input['search'] ?? ''), 'sort' => $input['sort'] ?? 'name'];
        $courses = Course::query()->select(['id', 'uuid', 'name', 'code', 'unit', 'created_at']);
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filters['search'])).'%';
            $courses->where(fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]));
        }
        match ($filters['sort']) {
            'code' => $courses->orderBy('code')->orderBy('id'),
            'newest' => $courses->orderByDesc('created_at')->orderByDesc('id'),
            default => $courses->orderBy('name')->orderBy('id'),
        };

        return Inertia::render('Dashboard/Course/Index', [
            'courses' => $courses->paginate(20)->withQueryString(),
            'filters' => $filters,
            'totalCourses' => Course::count(),
        ]);
    }

    public function add()
    {
        return Inertia::render('Dashboard/Course/Add');
    }

    public function addPost(Request $request): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', Rule::unique('courses', 'code')],
            'unit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);
        Course::create([...$input, 'uuid' => Str::uuid()]);

        return to_route('course', ['sort' => 'newest'])
            ->with('success', 'Course added successfully.');
    }

    public function edit(string $uuid)
    {
        $course = Course::where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Dashboard/Course/Edit', [
            'course' => $course,
        ]);
    }

    public function editPost(Request $request, string $uuid): \Illuminate\Http\RedirectResponse
    {
        $course = Course::where('uuid', $uuid)->firstOrFail();
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', Rule::unique('courses', 'code')->ignore($course)],
            'unit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);
        $course->update($input);

        return to_route('course', ['search' => $course->code])
            ->with('success', 'Course updated successfully.');
    }

    public function delete(Request $request)
    {
        Course::where('id', $request->id)->delete();

        return back()
            ->with('success', 'Course deleted successfully.');
    }
}
