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

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/Index', []);
    }
}
