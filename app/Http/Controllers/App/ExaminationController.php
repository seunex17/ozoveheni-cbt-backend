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
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $exam = Exam::where([
            'department_id' => $department,
            'set' => $set,
        ])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->firstOrFail();

        $now = now();

        $singleExam = SingleExam::where('exam_id', $exam->id)
            ->with(['course', 'exam'])
            ->firstOrFail();

        $questions = Question::where('single_exam_id', $singleExam->id)
            ->with(['options' => function ($query) {
                $query->inRandomOrder();
            }])
            ->inRandomOrder()
            ->get();

        Attempt::find($attempt)->update([
            'started_at' => $now,
        ]);

        return response()->json([
            'questions' => $questions,
            'singleExam' => $singleExam,
        ], ResponseAlias::HTTP_OK);
    }

    public function studentLogin(Request $request)
    {
        $input = $request->input();
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
            ->with(['course', 'exam'])
            ->first();

        if (! $singleExam) {
            return response()->json([
                'type' => 'error',
                'message' => 'You have no exam active yet please check back later.',
            ]);
        }

        if (! $singleExam->start->isToday()) {
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

        $attempt = Attempt::create([
            'student_id' => $student->id,
            'single_exam_id' => $singleExam->id,
        ]);

        return response()->json([
            'type' => 'start',
            'student' => $student,
            'attempt_id' => $attempt->id,
        ]);
    }


    /**
     * @throws \Throwable
     */
    public function saveAnswers(Request $request)
    {
        $answers = $request->input('answers');

        DB::transaction(function () use ($answers, $request) {
            foreach ($answers as $questionId => $optionId) {
                $isCorrect = QuestionOption::where('question_id', $questionId)
                    ->where('id', $optionId)
                    ->where('is_correct', true)
                    ->exists();

                Answer::updateOrCreate(
                    ['attempt_id' => $request->input('attempt_id'), 'question_id' => $questionId],
                    [
                        'option_id' => $optionId,
                        'is_correct' => $isCorrect,
                    ]
                );
            }
        });

        return response()->json(['status' => 'saved and graded']);
    }

    public function submitExam(Request $request)
    {
        Attempt::find($request->input('attempt_id'))->update([
            'submitted_at' => now(),
            'status' => 'completed',
        ]);

        return response()->json(['status' => 'Submitted successfully']);
    }
}
