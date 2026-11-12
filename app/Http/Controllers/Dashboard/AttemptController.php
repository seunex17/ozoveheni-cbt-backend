<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use App\Services\ExamHallMonitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttemptController extends Controller
{
    public function index(Request $request): Response
    {
        $input = $request->validate([
            'exam' => ['nullable', 'string', 'exists:exams,uuid'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $exam = isset($input['exam']) ? Exam::where('uuid', $input['exam'])->firstOrFail() : null;
        $search = trim($input['search'] ?? '');
        $students = Student::query()->select(['id', 'uuid', 'reg_no', 'first_name', 'middle_name', 'last_name']);
        if ($exam) {
            $scope = fn (Builder $query) => $query->whereHas('singleExam', fn (Builder $paper) => $paper->where('exam_id', $exam->id));
            $students->whereHas('attempts', $scope)->withCount([
                'attempts' => $scope,
                'attempts as submitted_count' => fn (Builder $query) => $scope($query)->where('status', 'completed'),
            ]);
        } else {
            $students->whereRaw('1 = 0');
        }
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';
            $students->where(function (Builder $query) use ($pattern): void {
                foreach (['reg_no', 'first_name', 'middle_name', 'last_name'] as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return Inertia::render('Dashboard/Attempts/Index', [
            'exams' => fn () => Exam::with('department:id,name')->select(['id', 'uuid', 'department_id', 'title', 'set', 'level'])->latest('id')->get(),
            'selectedExam' => $exam?->only(['uuid', 'title', 'set', 'level']),
            'filters' => ['exam' => $exam->uuid ?? '', 'search' => $search],
            'students' => $students->orderBy('reg_no')->orderBy('id')->paginate(20)->withQueryString(),
            'totalAttempts' => $exam ? $this->attempts($exam)->count() : 0,
        ]);
    }

    public function clearStudent(Request $request, Exam $exam, Student $student, ExamHallMonitor $monitor): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $count = DB::transaction(function () use ($exam, $student, $monitor): int {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $query = $this->attempts($exam)->where('student_id', $student->id);
            abort_unless((clone $query)->exists(), 404);
            $count = $query->delete();
            $monitor->publish('reset');

            return $count;
        });

        return redirect()->route('attempts', ['exam' => $exam->uuid, 'search' => $student->reg_no])
            ->with('success', "Cleared {$count} attempts for {$student->reg_no}. Stored results were kept.");
    }

    public function clearExam(Request $request, Exam $exam, ExamHallMonitor $monitor): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $count = DB::transaction(function () use ($exam, $monitor): int {
            Student::whereHas('attempts', fn (Builder $query) => $query->whereHas('singleExam', fn (Builder $paper) => $paper->where('exam_id', $exam->id)))
                ->orderBy('id')->lockForUpdate()->get(['id']);
            $count = $this->attempts($exam)->delete();
            $monitor->publish('reset');

            return $count;
        });

        return redirect()->route('attempts', ['exam' => $exam->uuid])
            ->with('success', "Cleared {$count} attempts for this exam. Stored results were kept.");
    }

    /** @return Builder<Attempt> */
    private function attempts(Exam $exam): Builder
    {
        return Attempt::whereHas('singleExam', fn (Builder $query) => $query->where('exam_id', $exam->id));
    }
}
