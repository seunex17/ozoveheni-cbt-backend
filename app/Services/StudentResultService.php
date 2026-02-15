<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: StudentResultService.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/15/26
 * Time: 7:53 AM
 */

namespace App\Services;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Report;
use App\Models\Student;

class StudentResultService
{
    public function __construct() {}

    public static function courseScore(Student $student, Course $course, Exam $exam, string $column)
    {
        return Report::where('student_id', $student->id)
            ->where('course_id', $course->id)->
            where('exam_id', $exam->id)
                ->first()->$column ?? 0;
    }

    public static function courseTotalScore(Student $student, Course $course, Exam $exam)
    {
        $result = Report::where('student_id', $student->id)
            ->where('course_id', $course->id)->
            where('exam_id', $exam->id)
                ->first();

        if (! $result) {
            return 0;
        }

        return $result->first_ca + $result->second_ca + $result->exam;
    }

    public static function courseGrade(Student $student, Course $course, Exam $exam): string
    {
        $score = self::courseTotalScore($student, $course, $exam);

        return match (true) {
            $score >= 70 => 'A',
            $score >= 65 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }
}
