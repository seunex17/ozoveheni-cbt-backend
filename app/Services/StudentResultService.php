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
            $score >= 55 => 'D',
            $score >= 50 => 'E',
            default => 'F',
        };
    }

    public static function courseGradePoint(Student $student, Course $course, Exam $exam): float
    {
        $score = self::courseTotalScore($student, $course, $exam);

        return match (true) {
            $score >= 70 => 4.00,
            $score >= 65 => 3.50,
            $score >= 60 => 3.00,
            $score >= 55 => 2.50,
            $score >= 50 => 2.00,
            default => 0.00,
        };
    }

    public static function calculateGPA(Student $student, Exam $exam): float
    {
        $reports = Report::where('student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->get();

        if ($reports->isEmpty()) {
            return 0.00;
        }

        $courseTot = 0;

        foreach ($reports as $report) {
            $courseTot += $report->first_ca + $report->second_ca + $report->exam;
        }

        $score = $courseTot / $reports->count();

        return match (true) {
            $score >= 70 => 4.00,
            $score >= 65 => 3.50,
            $score >= 60 => 3.00,
            $score >= 55 => 2.50,
            $score >= 50 => 2.00,
            default => 0.00,
        };
    }

    public static function remarks(Student $student, Exam $exam): string
    {
        $score = self::calculateGPA($student, $exam);

        return match (true) {
            $score >= 3.5 => 'Distinction',
            $score >= 3,0 => 'Upper Credit',
            $score >= 2.5 => 'Lower Credit',
            $score >= 2.0 => 'Pass',
            default => 'Fail',
        };
    }
}
