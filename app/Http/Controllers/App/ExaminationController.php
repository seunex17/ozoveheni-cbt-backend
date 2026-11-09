<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ExaminationController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/7/26
 * Time: 7:56 AM
 */

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use App\Services\ExamAttemptSession;
use App\Services\ExamHallMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExaminationController extends Controller
{
    public function __construct(private ExamAttemptSession $session) {}

    public function index()
    {
        //
    }

    public function loadQuestions(Request $request, string $department, string $set, string $attempt): JsonResponse
    {
        $set = Str::replace('-', '/', $set);

        return DB::transaction(function () use ($request, $department, $set, $attempt): JsonResponse {
            $examAttempt = Attempt::lockForUpdate()->findOrFail($attempt);
            $this->session->authorize($request, $examAttempt);
            abort_if($examAttempt->status === 'completed' || $examAttempt->submitted_at !== null, 409, 'Exam already submitted.');
            $singleExam = SingleExam::with(['course', 'exam'])->findOrFail($examAttempt->single_exam_id);
            abort_unless((string) $singleExam->exam->department_id === $department && $singleExam->exam->set === $set, 404);
            $examAttempt->setRelation('singleExam', $singleExam);
            $this->session->start($examAttempt);

            return response()->json([
                'questions' => $this->session->questions($examAttempt),
                'singleExam' => $singleExam,
                'answers' => $this->session->answers($examAttempt),
                'attempt_id' => $examAttempt->id,
                'attempt_status' => $examAttempt->status,
                ...$this->session->timing($examAttempt),
            ])->header('Cache-Control', 'no-store, private');
        });
    }

    public function studentLogin(Request $request): JsonResponse
    {
        $input = $request->validate([
            'reg_no' => ['required', 'string', 'max:255'],
            'client' => ['sometimes', 'in:flutter,legacy'],
            'login_id' => ['required_if:client,flutter', 'nullable', 'uuid:4'],
        ]);
        $student = Student::where('reg_no', $input['reg_no'])->first();
        if (! $student) {
            return response()->json(['type' => 'error', 'message' => 'Student does not exist']);
        }

        $flutter = ($input['client'] ?? 'legacy') === 'flutter';
        if ($flutter) {
            $input['login_id'] = Str::lower($input['login_id']);
        }
        $loginHash = $flutter ? hash('sha256', $input['login_id']) : null;
        $token = $flutter ? hash_hmac('sha256', $student->id.':'.$input['login_id'], config('app.key')) : null;
        if ($flutter) {
            $existing = Attempt::where('student_id', $student->id)->where('login_key_hash', $loginHash)->first();
            if ($existing) {
                if (! hash_equals($existing->access_token_hash, hash('sha256', $token))) {
                    $this->session->fail('INVALID_ATTEMPT_TOKEN', 'Login recovery credentials are invalid.', 401);
                }

                return $this->loginResponse($existing, $token);
            }
        }

        $today = now()->toDateString();
        $exam = Exam::where('department_id', $student->department_id)
            ->where('set', $student->set)->where('level', $student->level)
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first();
        if (! $exam) {
            return response()->json(['type' => 'error', 'message' => 'You have no exam active yet please check back later.']);
        }
        $paper = SingleExam::where('exam_id', $exam->id)
            ->where('start', '<=', now())->where('end', '>', now())->first();
        if (! $paper) {
            return response()->json(['type' => 'error', 'message' => 'No examination is currently accepting students.']);
        }

        $attempt = DB::transaction(function () use ($student, $paper, $loginHash, $token): ?Attempt {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($loginHash !== null) {
                $recovered = Attempt::where('student_id', $student->id)->where('login_key_hash', $loginHash)->first();
                if ($recovered) {
                    return $recovered;
                }
            }
            $existing = Attempt::where('student_id', $student->id)->where('single_exam_id', $paper->id)->first();
            if ($existing) {
                if ($loginHash !== null && $existing->login_key_hash === $loginHash) {
                    return $existing;
                }

                return null;
            }

            return Attempt::create([
                'student_id' => $student->id,
                'single_exam_id' => $paper->id,
                'access_token_hash' => $token === null ? null : hash('sha256', $token),
                'login_key_hash' => $loginHash,
            ]);
        });
        if (! $attempt) {
            return response()->json(['type' => 'error', 'message' => 'You have already taken this exam. Resume with the original attempt credentials.']);
        }

        return $this->loginResponse($attempt, $token);
    }

    private function loginResponse(Attempt $attempt, ?string $token): JsonResponse
    {
        return DB::transaction(function () use ($attempt, $token): JsonResponse {
            $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);
            $this->registerInHall($attempt);

            return response()->json([
                'type' => 'start',
                'student' => $attempt->student,
                'attempt_id' => $attempt->id,
                'attempt_token' => $token,
                'attempt_status' => $attempt->status,
                'hall_token' => null,
                'heartbeat_interval_seconds' => null,
                'hall_heartbeat_required' => false,
                'receipt' => $attempt->status === 'completed' ? $this->session->receipt($attempt) : null,
                ...$this->session->timing($attempt),
            ])->header('Cache-Control', 'no-store, private');
        });
    }

    private function registerInHall(Attempt $attempt): void
    {
        if ($attempt->status === 'completed' || $attempt->submitted_at !== null) {
            return;
        }
        $hall = ExamHall::firstOrCreate(['attempt_id' => $attempt->id], ['student_id' => $attempt->student_id]);
        if ($hall->wasRecentlyCreated) {
            app(ExamHallMonitor::class)->publish('upsert', $attempt->id);
        }
    }

    public function attemptStatus(Request $request, int $attempt): JsonResponse
    {
        $examAttempt = Attempt::findOrFail($attempt);
        $this->session->authorize($request, $examAttempt, true);

        return response()->json($this->sessionState($examAttempt))->header('Cache-Control', 'no-store, private');
    }

    public function resumeExam(Request $request): JsonResponse
    {
        $input = $request->validate(['attempt_id' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($input, $request): JsonResponse {
            $attempt = Attempt::lockForUpdate()->findOrFail($input['attempt_id']);
            $this->session->authorize($request, $attempt, true);
            $payload = $this->sessionState($attempt);
            $payload['student'] = $attempt->student;
            $payload['singleExam'] = $attempt->singleExam()->with(['course', 'exam'])->firstOrFail();
            $payload['questions'] = $attempt->status === 'completed' ? [] : $this->session->questions($attempt);
            $this->registerInHall($attempt);
            $payload['hall_token'] = null;
            $payload['heartbeat_interval_seconds'] = null;
            $payload['hall_heartbeat_required'] = false;

            return response()->json($payload)->header('Cache-Control', 'no-store, private');
        });
    }

    private function sessionState(Attempt $attempt): array
    {
        return [
            'success' => true,
            'attempt_id' => $attempt->id,
            'attempt_status' => $attempt->status,
            'answers' => $this->session->answers($attempt),
            'receipt' => $attempt->status === 'completed' ? $this->session->receipt($attempt) : null,
            ...$this->session->timing($attempt),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function saveAnswers(Request $request)
    {
        $receivedAt = now();
        $input = $request->validate([
            'attempt_id' => ['required', 'integer', 'min:1'],
            'answers' => ['present', 'array', 'max:1000'],
            'answers.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $completed = DB::transaction(function () use ($input, $request, $receivedAt): bool {
            $attempt = Attempt::lockForUpdate()->findOrFail($input['attempt_id']);
            $this->session->authorize($request, $attempt);
            if ($attempt->status === 'completed' || $attempt->submitted_at !== null) {
                if (! $this->session->unchanged($attempt, $input['answers'])) {
                    $this->session->fail('ATTEMPT_COMPLETED', 'Completed answers cannot be changed.');
                }

                return true;
            }
            $this->session->requireStarted($attempt);
            if ($attempt->expires_at !== null && $receivedAt->gte($attempt->expires_at)) {
                $this->session->fail('ATTEMPT_EXPIRED', 'The answering time has ended. Submit the frozen final answers.');
            }
            $this->persistAnswers($attempt, $input['answers']);

            return false;
        });

        return response()->json(['success' => true, 'status' => 'Score synchronized', 'completed' => $completed])->header('Cache-Control', 'no-store, private');
    }

    public function submitExam(Request $request)
    {
        $receivedAt = now();
        $input = $request->validate([
            'attempt_id' => ['required', 'integer', 'min:1'],
            'answers' => ['sometimes', 'array', 'max:1000'],
            'answers.*' => ['nullable', 'integer', 'min:1'],
            'submission_id' => ['sometimes', 'uuid'],
            'submit_saved_answers' => ['sometimes', 'boolean'],
        ]);
        if (isset($input['submission_id'])) {
            $input['submission_id'] = Str::lower($input['submission_id']);
        }

        $receipt = DB::transaction(function () use ($input, $request, $receivedAt): array {
            $attempt = Attempt::lockForUpdate()->findOrFail($input['attempt_id']);
            $this->session->authorize($request, $attempt);
            $savedOnly = (bool) ($input['submit_saved_answers'] ?? false);
            if ($savedOnly && array_key_exists('answers', $input)) {
                throw ValidationException::withMessages(['answers' => 'Omit answers when submitting only the saved server answers.']);
            }
            if ($attempt->access_token_hash !== null) {
                if (! isset($input['submission_id']) || (! $savedOnly && ! array_key_exists('answers', $input))) {
                    throw ValidationException::withMessages(['submission_id' => 'Flutter submissions require a UUID and final answers, or explicit submit_saved_answers.']);
                }
            }
            $payloadHash = $this->session->payloadHash($input['answers'] ?? [], $savedOnly);
            if ($attempt->status === 'completed' || $attempt->submitted_at !== null) {
                if (isset($input['submission_id']) && ($attempt->submission_id !== $input['submission_id'] || $attempt->submission_payload_hash !== $payloadHash)) {
                    $this->session->fail('SUBMISSION_CONFLICT', 'This attempt already has a different completed submission. Retrieve its receipt.');
                }

                return $this->session->receipt($attempt);
            }

            $this->session->requireStarted($attempt);
            if ($attempt->expires_at !== null && $receivedAt->gt($attempt->expires_at->copy()->addSeconds(ExamAttemptSession::FINAL_DELIVERY_GRACE_SECONDS))) {
                if (! $savedOnly && ! $this->session->unchanged($attempt, $input['answers'] ?? [])) {
                    $this->session->fail('FINAL_DELIVERY_EXPIRED', 'The final-delivery grace period has ended. Only previously saved answers can be submitted.');
                }
            }
            if (array_key_exists('answers', $input)) {
                $this->persistAnswers($attempt, $input['answers'], false);
            }
            $this->synchronizeReport($attempt);
            $attempt->update([
                'submitted_at' => now(),
                'status' => 'completed',
                'submission_id' => $input['submission_id'] ?? (string) Str::uuid(),
                'submission_payload_hash' => $payloadHash,
            ]);
            app(ExamHallMonitor::class)->publish('upsert', $attempt->id);

            return $this->session->receipt($attempt);
        });

        return response()->json($receipt)->header('Cache-Control', 'no-store, private');
    }

    /**
     * The caller must hold the attempt row lock until answers and marks commit.
     * Omitted questions are unchanged; an explicit null clears an answer.
     */
    private function persistAnswers(Attempt $attempt, array $answers, bool $synchronizeReport = true): void
    {
        if ($answers === []) {
            return;
        }

        foreach (array_keys($answers) as $questionId) {
            if (! ctype_digit((string) $questionId) || (int) $questionId < 1 || (string) (int) $questionId !== (string) $questionId) {
                throw ValidationException::withMessages(['answers' => 'Question IDs must be positive integers.']);
            }
        }

        if ($attempt->question_order !== null) {
            $allocated = array_column($attempt->question_order, 'id');
            if (array_diff(array_keys($answers), $allocated) !== []) {
                throw ValidationException::withMessages(['answers' => 'A question was not allocated to this attempt.']);
            }
        }

        $questions = Question::where('single_exam_id', $attempt->single_exam_id)
            ->whereIn('id', array_keys($answers))->pluck('id');
        $options = QuestionOption::whereIn('question_id', $questions)
            ->whereIn('id', array_values(array_filter($answers, fn ($value) => $value !== null)))
            ->get()->keyBy('id');

        if ($questions->count() !== count($answers)) {
            throw ValidationException::withMessages(['answers' => 'A question does not belong to this exam.']);
        }

        foreach ($answers as $questionId => $optionId) {
            if ($optionId !== null && (! isset($options[$optionId]) || (int) $options[$optionId]->question_id !== (int) $questionId)) {
                throw ValidationException::withMessages(['answers' => 'An option does not belong to its question.']);
            }
            if ($optionId !== null && $attempt->question_order !== null) {
                $allocation = collect($attempt->question_order)->firstWhere('id', $questionId);
                if (! in_array((int) $optionId, $allocation['option_ids'], true)) {
                    throw ValidationException::withMessages(['answers' => 'An option was not allocated to this attempt.']);
                }
            }
        }

        $existing = Answer::where('attempt_id', $attempt->id)
            ->whereIn('question_id', array_keys($answers))->get()->keyBy('question_id');
        $inserts = [];
        $changed = false;
        $now = now();
        foreach ($answers as $questionId => $optionId) {
            $correct = $optionId !== null && $options[$optionId]->is_correct;
            $answer = $existing->get($questionId);
            if ($answer) {
                $answer->fill(['option_id' => $optionId, 'is_correct' => $correct]);
                if ($answer->isDirty()) {
                    $answer->save();
                    $changed = true;
                }
            } elseif ($optionId !== null) {
                $inserts[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                    'option_id' => $optionId,
                    'is_correct' => $correct,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($inserts !== []) {
            Answer::insert($inserts);
            $changed = true;
        }
        if (! $changed) {
            return;
        }

        if ($synchronizeReport) {
            $this->synchronizeReport($attempt);
        }
    }

    private function synchronizeReport(Attempt $attempt): void
    {
        $totalScore = Answer::where('attempt_id', $attempt->id)
            ->where('is_correct', true)
            ->join('questions', 'answers.question_id', '=', 'questions.id')
            ->sum('questions.marks');

        /** @var SingleExam $singleExam */
        $singleExam = $attempt->singleExam;
        Report::updateOrCreate([
            'student_id' => $attempt->student_id,
            'exam_id' => $singleExam->exam_id,
            'course_id' => $singleExam->course_id,
        ], ['exam' => $totalScore]);
    }
}
