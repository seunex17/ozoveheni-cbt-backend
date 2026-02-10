<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ReportService.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/10/26
 * Time: 10:35 PM
 */

namespace App\Services;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Report;
use App\Models\Student;

class ReportService
{
    public function __construct() {}

    public static function score(Exam $exam, Student $student, Course $course)
    {
        $report = Report::where([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
        ])->first();

        if (! $report) {
            return '0';
        }

        return $report->first_ca + $report->second_ca + $report->exam;
    }
}
