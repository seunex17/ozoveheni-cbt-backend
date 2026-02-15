<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ReportController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/9/26
 * Time: 10:31 PM
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\SingleExam;
use App\Models\Student;
use Spatie\Browsershot\Browsershot;

class ReportController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function index(string $uuid)
    {
        $exam = Exam::with('department')->where('uuid', $uuid)->firstOrFail();
        $students = Student::where('department_id', $exam->department_id)
            ->where('set', $exam->set)
            ->orderBy('reg_no', 'ASC')
            ->get();

        $singleExam = SingleExam::with([
            'course',
        ])
            ->where('exam_id', $exam->id)
            ->get();

        $data = view('report', [
            'exam' => $exam,
            'students' => $students,
            'singleExams' => $singleExam,
        ])->render();

        $pdf = Browsershot::html($data)
            ->setChromePath(config('pdf.chrome_path'))
            ->setNodeBinary(config('pdf.node_binary'))
            ->setNpmBinary(config('pdf.npm_binary'))
            ->format('A3')
            ->landscape()
            ->noSandbox()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice.pdf"');
    }

    public function studentResultSlip()
    {
        $uuid = "7337071f-ba22-443a-94d5-8e2d193bb329";
        $exam = Exam::with('department')->where('uuid', $uuid)->firstOrFail();
        $student = Student::where('department_id', $exam->department_id)
            ->where('set', $exam->set)
            ->where('id', 3)
            ->firstOrFail();

        $singleExams = SingleExam::with([
            'course',
        ])
            ->where('exam_id', $exam->id)
            ->get();

        $data = view('student-result-slip', [
            'exam' => $exam,
            'student' => $student,
            'singleExams' => $singleExams,
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
