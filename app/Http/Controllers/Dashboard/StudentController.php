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
use App\Models\Level;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'uuid'],
            'set' => ['nullable', 'string', 'max:30'],
            'level' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'in:name,registration,newest'],
        ]);
        $filters = [
            'search' => trim($input['search'] ?? ''),
            'department' => $input['department'] ?? '',
            'set' => $input['set'] ?? '',
            'level' => Str::lower($input['level'] ?? ''),
            'sort' => $input['sort'] ?? 'name',
        ];
        $students = Student::query()->select([
            'id', 'uuid', 'department_id', 'first_name', 'middle_name', 'last_name', 'reg_no', 'set', 'level', 'photo',
        ])->with('department:id,name,uuid');
        if ($filters['search'] !== '') {
            $terms = preg_split('/\s+/', $filters['search']) ?: [];
            foreach ($terms as $term) {
                $students->where(function (Builder $query) use ($term): void {
                    foreach (['first_name', 'middle_name', 'last_name', 'reg_no'] as $column) {
                        $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%']);
                    }
                });
            }
        }
        if ($filters['department'] !== '') {
            $students->whereHas('department', fn (Builder $query) => $query->where('uuid', $filters['department']));
        }
        if ($filters['set'] !== '') {
            $students->where('set', $filters['set']);
        }
        if ($filters['level'] !== '') {
            $students->whereRaw('LOWER(level) = ?', [$filters['level']]);
        }
        match ($filters['sort']) {
            'registration' => $students->orderBy('reg_no')->orderBy('id'),
            'newest' => $students->orderByDesc('created_at')->orderByDesc('id'),
            default => $students->orderBy('first_name')->orderBy('last_name')->orderBy('id'),
        };

        return Inertia::render('Student/Index', [
            'students' => $students->paginate(20)->withQueryString(),
            'departments' => fn () => Department::orderBy('name')->get(['id', 'uuid', 'name']),
            'sets' => fn () => Student::query()->select('set')->distinct()->orderByDesc('set')->pluck('set')->filter()->values(),
            'levels' => fn () => Level::orderBy('id')->pluck('code'),
            'filters' => $filters,
            'totalStudents' => Student::count(),
        ]);
    }

    public function department(string $uuid, string $set, string $level): \Illuminate\Http\RedirectResponse
    {
        $set = Str::replace('-', '/', $set);
        $department = Department::where('uuid', $uuid)->firstOrFail();

        return to_route('student', [
            'department' => $department->uuid,
            'set' => $set,
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
            'levels' => Level::orderBy('id')->pluck('code'),
        ]);
    }

    public function addPost(Request $request)
    {
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'level' => ['required', 'string', 'exists:levels,code'],
            'reg_no' => 'required|unique:students,reg_no',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validate->fails()) {
            return back()
                ->withErrors($validate)->withInput();
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
            'levels' => Level::orderBy('id')->pluck('code'),
        ]);
    }

    public function editPost(Request $request)
    {
        $input = $request->input();

        $validate = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'level' => ['required', 'string', 'exists:levels,code'],
            'photo' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validate->fails()) {
            return back()
                ->withErrors($validate)->withInput();
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
