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
    public function index()
    {
        $exams = Exam::latest('created_at')->get();

        return Inertia::render('Dashboard/ExamVoucher/Index', [
            'exams' => $exams,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function generate(Request $request)
    {
        $exam = Exam::findOrFail($request->input('exam_id'));
        $ref = ExamVoucherService::generate($request->input('qty'), $exam);

        $vouchers = Voucher::where('reference', $ref)
            ->get();

        $data = view('exam.voucher', [
            'vouchers' => $vouchers,
        ])->render();

        $pdf = Browsershot::html($data)
            ->setChromePath(config('pdf.chrome_path'))
            ->setNodeBinary(config('pdf.node_binary'))
            ->setNpmBinary(config('pdf.npm_binary'))
            ->noSandbox()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice.pdf"');
    }
}
