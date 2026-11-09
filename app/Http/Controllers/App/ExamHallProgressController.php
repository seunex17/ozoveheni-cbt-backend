<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExamHallProgressController extends Controller
{
    public function store(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'HALL_HEARTBEAT_RETIRED',
            'message' => 'Stop hall heartbeat and question-progress requests. Hall status now updates from login and submission.',
            'heartbeat_required' => false,
        ], 410);
    }
}
