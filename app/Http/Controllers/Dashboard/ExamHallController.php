<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\ExamHallMonitor;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExamHallController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard/ExamHall/Index');
    }

    public function snapshot(ExamHallMonitor $monitor): JsonResponse
    {
        return response()->json($monitor->snapshot())->header('Cache-Control', 'no-store, private');
    }
}
