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

use App\Events\ExamHallEvent;
use App\Events\SeepExamHallEvent;
use App\Http\Controllers\Controller;
use App\Models\ExamHall;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/Index', []);
    }

    public function sweepExamHall()
    {
        ExamHall::truncate();

        broadcast(new SeepExamHallEvent)->toOthers();
        broadcast(new ExamHallEvent(ExamHall::count()))->toOthers();

        return back()
            ->with('success', 'Hall has been swept.');
    }
}
