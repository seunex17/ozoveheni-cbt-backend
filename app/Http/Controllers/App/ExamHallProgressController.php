<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\ExamHall;
use App\Models\Question;
use App\Services\ExamHallMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamHallProgressController extends Controller
{
    public function store(Request $request, ExamHallMonitor $monitor): JsonResponse
    {
        $token = $request->bearerToken();
        abort_if($token === null || $token === '', 401, 'A hall token is required.');

        $input = $request->validate([
            'attempt_id' => ['required', 'integer', 'min:1'],
            'sequence' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'current_question_id' => ['nullable', 'integer', 'min:1'],
            'current_question_number' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:current_question_id,total_questions'],
            'total_questions' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:current_question_id,current_question_number'],
        ]);

        $result = DB::transaction(function () use ($input, $token, $monitor): array {
            $attempt = Attempt::whereKey($input['attempt_id'])->lockForUpdate()->first();
            $hall = ExamHall::where('attempt_id', $input['attempt_id'])
                ->where('monitor_token_hash', hash('sha256', $token))
                ->first();

            abort_unless($attempt && $hall, 401, 'Invalid or expired hall token.');
            abort_if($attempt->status === 'completed' || $attempt->submitted_at !== null, 409, 'Exam already submitted.');

            if ($input['sequence'] <= $hall->last_sequence) {
                return ['accepted' => false, 'last_sequence' => $hall->last_sequence];
            }

            if (isset($input['current_question_number']) && ! isset($input['current_question_id'])) {
                throw ValidationException::withMessages(['current_question_id' => 'A question ID is required when reporting progress.']);
            }

            if (isset($input['current_question_id'])) {
                if ($input['current_question_number'] > $input['total_questions']) {
                    throw ValidationException::withMessages(['current_question_number' => 'The question number cannot exceed the paper length.']);
                }

                if ($attempt->question_order !== null) {
                    $order = $attempt->question_order;
                    $allocated = $order[$input['current_question_number'] - 1] ?? null;
                    if ($allocated === null || (int) $allocated['id'] !== (int) $input['current_question_id'] || count($order) !== (int) $input['total_questions']) {
                        throw ValidationException::withMessages(['current_question_id' => 'The question position does not match the allocated paper.']);
                    }
                } else {
                    $question = Question::where('single_exam_id', $attempt->single_exam_id)
                        ->whereKey($input['current_question_id'])->exists();
                    $paperLength = min(60, Question::where('single_exam_id', $attempt->single_exam_id)->count());
                    if (! $question || $input['total_questions'] > $paperLength) {
                        throw ValidationException::withMessages(['current_question_id' => 'The question or paper length does not match this exam.']);
                    }
                }

                $hall->fill([
                    'current_question_id' => $input['current_question_id'],
                    'current_question_number' => $input['current_question_number'],
                    'total_questions' => $input['total_questions'],
                ]);
            }

            $hall->last_sequence = $input['sequence'];
            $hall->last_seen_at = now();
            $hall->save();
            $monitor->publish('progress', $attempt->id, $monitor->progress($hall));

            return ['accepted' => true, 'last_sequence' => $hall->last_sequence];
        });

        return response()->json([
            ...$result,
            'server_time' => now()->toISOString(),
            'heartbeat_interval_seconds' => ExamHallMonitor::HEARTBEAT_SECONDS,
        ]);
    }
}
