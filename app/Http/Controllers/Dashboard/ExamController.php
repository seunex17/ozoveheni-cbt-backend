<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ExamController.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/6/26
 * Time: 8:59 AM
 */

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\SingleExam;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::latest('created_at')
            ->with('department')
            ->paginate();

        return Inertia::render('Dashboard/Exams/Index', [
            'exams' => Inertia::scroll(fn () => $exams),
        ]);
    }

    public function add()
    {
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Dashboard/Exams/Add', [
            'departments' => $departments,
        ]);
    }

    public function addPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'department_id' => 'required',
            'title' => 'required',
            'set' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $input = $request->input();
        $input['uuid'] = Str::uuid();

        Exam::create($input);

        return redirect()
            ->route('exam')
            ->with('success', 'Exam created successfully.');
    }

    public function edit(string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Dashboard/Exams/Edit', [
            'exam' => $exam,
            'departments' => $departments,
        ]);
    }

    public function editPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'department_id' => 'required',
            'title' => 'required',
            'set' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $input = $request->input();
        $exam = Exam::findOrFail($input['id']);
        $exam->update($input);

        return redirect()
            ->route('exam')
            ->with('success', 'Exam updated successfully.');
    }

    public function view(string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $singleExam = SingleExam::where('exam_id', $exam->id)
            ->with('course')
            ->paginate();

        return Inertia::render('Dashboard/Exams/View', [
            'exam' => $exam,
            'singleExams' => Inertia::scroll(fn () => $singleExam),
        ]);
    }

    public function setNewExam(string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $courses = Course::orderBy('name')->get();

        return Inertia::render('Dashboard/Exams/SetNewExam', [
            'exam' => $exam,
            'courses' => $courses,
        ]);
    }

    public function setNewExamPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'exam_id' => 'required',
            'course_id' => 'required',
            'schedule_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration' => 'required',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $exam = Exam::findOrFail($request->input('exam_id'));

        if (SingleExam::where([
            'exam_id' => $exam->id,
            'course_id' => $request->input('course_id'),
        ])->exists()) {
            return back()
                ->with('error', 'Exam already exists.');
        }

        $input = $request->input();
        $input['uuid'] = Str::uuid();
        $input['start'] = Carbon::parse($input['schedule_date'].' '.$input['start_time'])->format('F j, Y, g:i a');
        $input['end'] = Carbon::parse($input['schedule_date'].' '.$input['end_time'])->format('F j, Y, g:i a');

        SingleExam::create($input);

        return redirect()
            ->route('exam.view', $exam->uuid)
            ->with('success', 'Exam created successfully.');
    }

    public function singleExam(string $uuid)
    {
        $singleExam = SingleExam::where('uuid', $uuid)->firstOrFail()
            ->with(['exam', 'course'])
            ->firstOrFail();
        $questions = Question::where('single_exam_id', $singleExam->id)
            ->withCount('options')
            ->paginate();

        return Inertia::render('Dashboard/Exams/Single/Index', [
            'singleExam' => $singleExam,
            'questions' => Inertia::scroll(fn () => $questions),
        ]);
    }

    public function addQuestion(string $uuid)
    {
        $singleExam = SingleExam::where('uuid', $uuid)->firstOrFail()
            ->with(['exam', 'course'])
            ->firstOrFail();

        return Inertia::render('Dashboard/Exams/Single/AddQuestion', [
            'singleExam' => $singleExam,
        ]);
    }

    public function addQuestionPost(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'points' => 'required|integer',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $input = $request->input();

        $question = Question::create([
            'question_text' => $input['question_text'],
            'marks' => $input['points'],
            'single_exam_id' => $input['single_exam_id'],
        ]);

        $question->options()->createMany($input['options']);

        return back()
            ->with('success', 'Question created successfully.');
    }

    public function viewQuestion(string $id)
    {
        $question = Question::with('options')->findOrFail($id);

        return Inertia::render('Dashboard/Exams/Single/ViewQuestion', [
            'question' => $question,
        ]);
    }

    public function deleteQuestionPost()
    {
        Question::destroy(request()->input('id'));

        return back()
            ->with('success', 'Question deleted successfully.');
    }
}
