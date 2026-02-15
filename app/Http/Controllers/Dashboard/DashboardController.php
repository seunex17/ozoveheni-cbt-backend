<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: DashboardController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/4/26
 * Time: 8:28 PM
 */

namespace App\Http\Controllers\Dashboard;

use App\Events\CloseExamHallEvent;
use App\Events\ExamHallEvent;
use App\Events\SeepExamHallEvent;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\Question;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalStudents = Student::all()->count();
        $activeExams = Exam::where('status', 'active')->count();
        $questionsBank = Question::all()->count();
        $departments = Department::all()->count();

        $upcomingExams = SingleExam::query()
            ->with(['course', 'exam.department'])
            ->whereBetween('start', [Carbon::now(), Carbon::now()->addDays(7)])
            ->get();

        return Inertia::render('Dashboard/Index', [
            'totalStudents' => $totalStudents,
            'activeExams' => $activeExams,
            'questionsBank' => $questionsBank,
            'departments' => $departments,
            'upcomingExams' => $upcomingExams,
        ]);
    }

    public function sweepExamHall()
    {
        ExamHall::truncate();

        broadcast(new SeepExamHallEvent)->toOthers();
        broadcast(new ExamHallEvent(ExamHall::count()))->toOthers();

        return back()
            ->with('success', 'Hall has been swept.');
    }

    public function closeExamAll()
    {
        broadcast(new CloseExamHallEvent)->toOthers();

        return back()
            ->with('success', 'Hall has been closed.');
    }
}
