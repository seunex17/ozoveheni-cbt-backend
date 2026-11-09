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
    public function index(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,newest'],
        ]);
        $filters = [
            'search' => trim($input['search'] ?? ''),
            'sort' => $input['sort'] ?? 'name',
        ];
        $departments = Department::query()->select(['id', 'uuid', 'name', 'created_at']);
        if ($filters['search'] !== '') {
            $departments->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filters['search'])).'%']);
        }
        match ($filters['sort']) {
            'newest' => $departments->orderByDesc('created_at')->orderByDesc('id'),
            default => $departments->orderBy('name')->orderBy('id'),
        };

        return Inertia::render('Dashboard/Department/Index', [
            'departments' => $departments->paginate(20)->withQueryString(),
            'filters' => $filters,
            'totalDepartments' => Department::count(),
        ]);
    }

    public function add()
    {
        return Inertia::render('Dashboard/Department/Add');
    }

    public function addPost(Request $request): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create([
            'name' => $input['name'],
            'uuid' => Str::uuid(),
        ]);

        return redirect()->route('department', ['sort' => 'newest'])
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
