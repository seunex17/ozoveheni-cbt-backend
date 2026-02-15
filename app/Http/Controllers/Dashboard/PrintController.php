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
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PrintController extends Controller
{
    public function index()
    {
        //
    }

    public function result()
    {
        $exams = Exam::latest('created_at')->get();

        return Inertia::render('Dashboard/Print/Result', [
            'exams' => $exams,
        ]);
    }

    public function resultPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'exam_id' => 'required',
            'reg_no' => 'required',
            'pin' => 'required',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $exam = Exam::findOrFail($request->exam_id);

        $student = Student::where('reg_no', $request->reg_no)->first();

        if (! $student) {
            return back()
                ->with('error', 'Student Not Found');
        }

        $voucher = Voucher::where('pin', $request->pin)->first();

        if (! $voucher) {
            return back()
                ->with('error', 'Invalid Voucher Code');
        }

        $voucher->delete();

        return Inertia::render('Dashboard/Print/ResultGenerate', [
            'exam' => $exam,
            'pdfUrl' => route('studentResultSlip', [
                'exam' => $exam->uuid,
                'sid' => $student->id,
            ]),
        ]);
    }
}
