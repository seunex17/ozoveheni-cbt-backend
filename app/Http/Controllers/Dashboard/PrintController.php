<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: PrintController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/15/26
 * Time: 11:13 AM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Student;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PrintController extends Controller
{
    public function index()
    {
        //
    }

    public function result(): \Inertia\Response
    {
        $exams = Exam::query()->select(['id', 'title', 'subtitle', 'set', 'level', 'department_id'])
            ->with('department:id,name')->latest('created_at')->orderByDesc('id')->get();

        return Inertia::render('Dashboard/Print/Result', [
            'exams' => $exams,
        ]);
    }

    public function resultPost(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'reg_no' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{12}$/'],
        ]);
        $exam = Exam::findOrFail($input['exam_id']);
        $student = Student::where('reg_no', $input['reg_no'])
            ->where('department_id', $exam->department_id)->where('set', $exam->set)->first();

        if (! $student) {
            throw ValidationException::withMessages(['reg_no' => 'No student with this registration number was found in the selected examination’s department and set.']);
        }

        $consumed = Voucher::where('pin', $input['pin'])->where('exam_id', $exam->id)->delete();
        if ($consumed !== 1) {
            throw ValidationException::withMessages(['pin' => 'This voucher is invalid, already used, or belongs to another examination.']);
        }

        return Inertia::render('Dashboard/Print/ResultGenerate', [
            'exam' => $exam,
            'student' => $student->only(['first_name', 'middle_name', 'last_name', 'reg_no']),
            'pdfUrl' => route('studentResultSlip', [
                'exam' => $exam->uuid,
                'sid' => $student->id,
            ]),
        ]);
    }
}
