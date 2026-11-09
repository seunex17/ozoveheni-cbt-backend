<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ExamVoucherController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/11/26
 * Time: 12:41 PM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Voucher;
use App\Services\ExamVoucherService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;

class ExamVoucherController extends Controller
{
    public function index(): \Inertia\Response
    {
        $exams = Exam::query()->select(['id', 'title', 'subtitle', 'set', 'level', 'department_id'])
            ->with('department:id,name')->latest('created_at')->orderByDesc('id')->get();

        return Inertia::render('Dashboard/ExamVoucher/Index', [
            'exams' => $exams,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function generate(Request $request): \Illuminate\Http\Response
    {
        $input = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $exam = Exam::findOrFail($input['exam_id']);
        $ref = ExamVoucherService::generate((int) $input['qty'], $exam);

        $vouchers = Voucher::where('reference', $ref)
            ->get();

        $data = view('exam.voucher', [
            'vouchers' => $vouchers,
            'exam' => $exam,
        ])->render();

        $pdf = Browsershot::html($data)
            ->format('A4')
            ->showBackground()
            ->setChromePath(config('pdf.chrome_path'))
            ->setNodeBinary(config('pdf.node_binary'))
            ->setNpmBinary(config('pdf.npm_binary'))
            ->noSandbox()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="exam-vouchers.pdf"');
    }
}
