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
use App\Services\ExamHallMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ExaminationController extends Controller
{
    public function index()
    {
        //
    }

    public function loadQuestions(string $department, string $set, string $attempt)
    {
        $today = now()->toDateString();
        $set = Str::replace('-', '/', $set);

        $exam = Exam::where([
            'department_id' => $department,
            'set' => $set,
        ])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->firstOrFail();

        $now = now();

        $examAttempt = Attempt::findOrFail($attempt);
        abort_if($examAttempt->status === 'completed' || $examAttempt->submitted_at !== null, 409, 'Exam already submitted.');

        $singleExam = SingleExam::where('exam_id', $exam->id)
            ->whereKey($examAttempt->single_exam_id)
            ->with(['course', 'exam'])
            ->firstOrFail();

        $questions = Question::where('single_exam_id', $singleExam->id)
            ->with(['options' => function ($query) {
                $query->inRandomOrder();
            }])
            ->take(60)
            ->inRandomOrder()
            ->get();

        $questions->each(fn ($question) => $question->options->each->makeHidden('is_correct'));

        Attempt::whereKey($examAttempt->id)->whereNull('started_at')->update([
            'started_at' => $now,
        ]);

        return response()->json([
            'questions' => $questions,
            'singleExam' => $singleExam,
        ], ResponseAlias::HTTP_OK);
    }

    public function studentLogin(Request $request)
    {
        $input = $request->validate(['reg_no' => ['required', 'string', 'max:255']]);
        $regNo = $input['reg_no'];

        // Find Student
        $student = Student::where('reg_no', $regNo)->first();

        if (! $student) {
            return response()->json([
                'type' => 'error',
                'message' => 'Student does not exist',
            ]);
        }

        $today = now()->toDateString();

        $exam = Exam::where('department_id', $student->department_id)
            ->where('set', $student->set)
            ->where('level', $student->level)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (! $exam) {
            return response()->json([
                'type' => 'error',
                'message' => 'You have no exam active yet please check back later.',
            ]);
        }

        $singleExam = SingleExam::where('exam_id', $exam->id)
            ->where('start', '<=', now())
            ->where('end', '>=', now())
            ->with(['course', 'exam'])
            ->first();

        if (! $singleExam) {
            $nextExam = SingleExam::where('exam_id', $exam->id)
                ->where('start', '>', now())
                ->orderBy('start', 'asc')
                ->first();

            if ($nextExam) {
                return response()->json([
                    'type' => 'error',
                    'message' => 'Your next exam starts at '.$nextExam->start->toTimeString().'. Please check back later.',
                ]);
            }

            return response()->json([
                'type' => 'error',
                'message' => 'You have no exam active yet please check back later.',
            ]);
        }

        $attempt = Attempt::where([
            'student_id' => $student->id,
            'single_exam_id' => $singleExam->id,
        ])->first();

        if ($attempt) {
            return response()->json([
                'type' => 'error',
                'message' => 'You have already taken this exam.',
            ]);
        }

        if (now()->lt($singleExam->start)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Exam is yet to start. Please wait some moment'.now(),
            ]);
        }

        if (now()->gt($singleExam->end)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Exam has ended.',
            ]);
        }

        $hallToken = Str::random(64);
        $attempt = DB::transaction(function () use ($student, $singleExam, $hallToken): Attempt {
            $attempt = Attempt::create([
                'student_id' => $student->id,
                'single_exam_id' => $singleExam->id,
            ]);

            ExamHall::create([
                'student_id' => $student->id,
                'attempt_id' => $attempt->id,
                'monitor_token_hash' => hash('sha256', $hallToken),
            ]);
            app(ExamHallMonitor::class)->publish('refresh');

            return $attempt;
        });

        return response()->json([
            'type' => 'start',
            'student' => $student,
            'attempt_id' => $attempt->id,
            'hall_token' => $hallToken,
            'heartbeat_interval_seconds' => ExamHallMonitor::HEARTBEAT_SECONDS,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function saveAnswers(Request $request)
    {
        $input = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['required', 'array', 'max:1000'],
            'answers.*' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($input) {
            $attempt = Attempt::lockForUpdate()->findOrFail($input['attempt_id']);
            abort_if($attempt->status === 'completed' || $attempt->submitted_at !== null, 409, 'Exam already submitted.');
            $this->persistAnswers($attempt, $input['answers']);
        });

        return response()->json(['status' => 'Score synchronized']);
    }

    public function submitExam(Request $request)
    {
        $input = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['sometimes', 'array', 'max:1000'],
            'answers.*' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($input) {
            $attempt = Attempt::lockForUpdate()->findOrFail($input['attempt_id']);
            if ($attempt->status === 'completed' || $attempt->submitted_at !== null) {
                return;
            }

            if (array_key_exists('answers', $input)) {
                $this->persistAnswers($attempt, $input['answers']);
            }

            $attempt->update([
                'submitted_at' => now(),
                'status' => 'completed',
            ]);
            app(ExamHallMonitor::class)->publish('remove', $attempt->id);
        });

        return response()->json(['status' => 'Submitted successfully']);
    }

    /**
     * The caller must hold the attempt row lock until answers and marks commit.
     * Omitted questions are unchanged; an explicit null clears an answer.
     */
    private function persistAnswers(Attempt $attempt, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        foreach (array_keys($answers) as $questionId) {
            if (! ctype_digit((string) $questionId) || (int) $questionId < 1) {
                throw ValidationException::withMessages(['answers' => 'Question IDs must be positive integers.']);
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
