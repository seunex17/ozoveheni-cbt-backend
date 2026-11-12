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
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class ExamController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'uuid'],
            'set' => ['nullable', 'string', 'max:30'],
            'level' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:pending,active,completed'],
            'sort' => ['nullable', 'in:newest,title,start_date'],
        ]);
        $filters = [
            'search' => trim($input['search'] ?? ''), 'department' => $input['department'] ?? '',
            'set' => $input['set'] ?? '', 'level' => Str::lower($input['level'] ?? ''),
            'status' => $input['status'] ?? '', 'sort' => $input['sort'] ?? 'newest',
        ];
        $exams = Exam::query()->with('department:id,uuid,name');
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filters['search'])).'%';
            $exams->where(fn (Builder $query) => $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(subtitle) LIKE ? ESCAPE '!'", [$term]));
        }
        if ($filters['department'] !== '') {
            $exams->whereHas('department', fn (Builder $query) => $query->where('uuid', $filters['department']));
        }
        foreach (['set', 'status'] as $field) {
            if ($filters[$field] !== '') {
                $exams->where($field, $filters[$field]);
            }
        }
        if ($filters['level'] !== '') {
            $exams->whereRaw('LOWER(level) = ?', [$filters['level']]);
        }
        match ($filters['sort']) {
            'title' => $exams->orderBy('title')->orderBy('id'),
            'start_date' => $exams->orderByDesc('start_date')->orderByDesc('id'),
            default => $exams->orderByDesc('created_at')->orderByDesc('id'),
        };

        return Inertia::render('Dashboard/Exams/Index', [
            'exams' => $exams->paginate(20)->withQueryString(),
            'filters' => $filters, 'totalExams' => Exam::count(),
            'departments' => fn () => Department::orderBy('name')->get(['id', 'uuid', 'name']),
            'sets' => fn () => Exam::query()->select('set')->distinct()->orderByDesc('set')->pluck('set')->filter()->values(),
            'levels' => fn () => Level::orderBy('id')->pluck('code'),
        ]);
    }

    public function add()
    {
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Dashboard/Exams/Add', [
            'departments' => $departments,
            'levels' => Level::orderBy('id')->pluck('code'),
        ]);
    }

    public function addPost(Request $request): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'set' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'level' => ['required', 'string', 'exists:levels,code'],
        ]);
        Exam::create([...$input, 'uuid' => Str::uuid()]);

        return to_route('exam')->with('success', 'Exam created successfully.');
    }

    public function edit(string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $departments = Department::orderBy('name')->get();

        return Inertia::render('Dashboard/Exams/Edit', [
            'exam' => $exam,
            'departments' => $departments,
            'levels' => Level::orderBy('id')->pluck('code'),
        ]);
    }

    public function editPost(Request $request): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'id' => ['required', 'integer'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'set' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'level' => ['required', 'string', 'exists:levels,code'],
        ]);
        $exam = Exam::findOrFail($input['id']);
        unset($input['id']);
        $exam->update($input);

        return to_route('exam', ['search' => $exam->title])->with('success', 'Exam updated successfully.');
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

    public function broadsheet(string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $singleExam = SingleExam::where('exam_id', $exam->id)
            ->with('course')
            ->paginate();

        return Inertia::render('Dashboard/Exams/BroadSheet', [
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
            'duration' => 'required|integer|min:1',
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
        $singleExam = SingleExam::where('uuid', $uuid)
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

    public function addQuestion(string $uuid): \Inertia\Response
    {
        $singleExam = SingleExam::where('uuid', $uuid)
            ->with(['exam', 'course'])
            ->firstOrFail();

        return Inertia::render('Dashboard/Exams/Single/AddQuestion', [
            'singleExam' => $singleExam,
        ]);
    }

    public function addQuestionPost(Request $request, ?string $uuid = null): \Illuminate\Http\RedirectResponse
    {
        $input = $request->validate([
            'single_exam_id' => ['required', 'integer', 'exists:single_exams,id'],
            'question_text' => 'required|string',
            'points' => 'required|integer',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        $singleExam = $uuid === null
            ? SingleExam::findOrFail($input['single_exam_id'])
            : SingleExam::where('uuid', $uuid)->firstOrFail();
        if ((int) $input['single_exam_id'] !== $singleExam->id) {
            throw ValidationException::withMessages(['single_exam_id' => 'The selected paper has changed. Reopen the question editor from the correct course paper.']);
        }

        DB::transaction(function () use ($input, $singleExam): void {
            $question = $singleExam->questions()->create([
                'question_text' => $input['question_text'],
                'marks' => $input['points'],
            ]);
            $question->options()->createMany($input['options']);
        });

        return to_route('exam.addQuestion', $singleExam->uuid)
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

    public function deleteExamPost()
    {
        Exam::destroy(request()->input('id'));

        return back()
            ->with('success', 'Exam deleted successfully.');
    }

    public function reports(Request $request, string $uuid)
    {
        $exam = Exam::where('uuid', $uuid)->firstOrFail();
        $courses = Course::orderBy('name')->get();
        $courseUUid = $request->get('course_uuid') ?? null;

        if ($courseUUid) {
            $course = Course::where('uuid', $courseUUid)->firstOrFail();
            $reports = Report::where([
                'exam_id' => $exam->id,
                'course_id' => $course->id,
            ])
                ->with('student')
                ->get()->map(function ($report) {
                    return [
                        'name' => $report->student->first_name.' '.$report->student->last_name,
                        'reg_no' => $report->student->reg_no,
                        'first_ca' => $report->first_ca,
                        'second_ca' => $report->second_ca,
                        'total' => $report->total_ca,
                        'exam' => $report->exam,
                        'id' => $report->id,
                    ];
                });
        }

        return Inertia::render('Dashboard/Exams/Report', [
            'exam' => $exam,
            'courseUUid' => $courseUUid,
            'courses' => $courses,
            'course' => $course ?? null,
            'reports' => $reports ?? null,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function refreshReports(Request $request)
    {
        $exam = Exam::findOrFail($request->input('exam_id'));

        \DB::transaction(function () use ($request, $exam) {
            $students = Student::where([
                'set' => $exam->set,
                'department_id' => $exam->department_id,
            ])->get();

            foreach ($students as $student) {
                Report::updateOrCreate([
                    'exam_id' => $request->input('exam_id'),
                    'course_id' => $request->input('course_id'),
                    'student_id' => $student->id,
                ]);
            }
        });

        return back()
            ->with('success', 'Report updated successfully.');
    }

    public function updateReport(Request $request)
    {
        Report::find($request->input('id'))
            ->update($request->input());
    }

    public function downloadTemplate()
    {
        if (Storage::disk('public')->exists('exam/template.xlsx')) {
            return Storage::disk('public')->download('exam/template.xlsx');
        }

        abort(404);
    }

    /**
     * @throws \OpenSpout\Common\Exception\IOException
     * @throws \OpenSpout\Common\Exception\UnsupportedTypeException
     * @throws \OpenSpout\Reader\Exception\ReaderNotOpenedException
     */
    public function importQuestions(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx',
        ]);

        if ($validate->fails()) {
            return back()
                ->with('error', $validate->errors()->first());
        }

        $examId = request()->input('exam_id');

        (new FastExcel)->import($request->file('file'), function ($line) use ($examId) {
            $question = Question::create([
                'single_exam_id' => $examId,
                'question_text' => $line['question_text'],
                'marks' => $line['marks'] ?? 1,
            ]);

            for ($i = 1; $i <= 4; $i++) {
                $optionText = $line["option_$i"] ?? null;

                if ($optionText) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optionText,
                        'is_correct' => (int) $line['correct_option_number'] === $i,
                    ]);
                }
            }
        });

        return back()->with('success', 'Questions imported successfully!');
    }
}
