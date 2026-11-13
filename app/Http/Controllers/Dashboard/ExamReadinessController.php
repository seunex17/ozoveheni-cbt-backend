<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class ExamReadinessController extends Controller
{
    public function __invoke(string $uuid): Response
    {
        $exam = Exam::with('department:id,uuid,name')->where('uuid', $uuid)->firstOrFail();
        $levels = Level::pluck('name', 'code');
        $papers = SingleExam::where('exam_id', $exam->id)->with('course:id,name,code')->orderBy('start')->orderBy('id')->get();
        $questions = Question::whereIn('single_exam_id', $papers->modelKeys())
            ->select(['id', 'single_exam_id', 'question_text', 'marks'])
            ->withCount(['options', 'options as correct_count' => fn (Builder $query) => $query->where('is_correct', true)])
            ->orderBy('id')->get()->groupBy('single_exam_id');
        $checks = [];
        $add = function (string $id, string $title, string $severity, string $description, int $count, array $items = [], ?string $href = null) use (&$checks): void {
            $checks[] = compact('id', 'title', 'description', 'count', 'href') + [
                'status' => $count > 0 ? $severity : 'passed',
                'items' => array_slice($items, 0, 20),
            ];
        };
        $editExam = route('exam.edit', $exam->uuid);
        $paperPage = route('exam.view', $exam->uuid);
        $validDates = $exam->start_date && $exam->end_date && $exam->start_date->lte($exam->end_date);
        $add('exam_dates', 'Exam dates', 'blocker', 'The exam start date must be on or before its end date.', $validDates ? 0 : 1, [], $editExam);
        $add('exam_level', 'Exam level', 'blocker', 'The assigned level must exist in the levels database.', $levels->has($exam->level) ? 0 : 1, [], $editExam);
        $add('papers', 'Course papers', 'blocker', 'Add at least one course paper before admitting students.', $papers->isEmpty() ? 1 : 0, [], route('exam.setNewExam', $exam->uuid));
        $empty = $invalidQuestions = $multipleCorrect = $shortPapers = $schedules = $overlaps = $closed = [];
        foreach ($papers as $index => $paper) {
            $label = $paper->course->code.' · '.$paper->course->name;
            $bank = $questions->get($paper->id, collect());
            $questionLink = route('exam.singleExam', $paper->uuid);
            if ($bank->isEmpty()) {
                $empty[] = ['label' => $label, 'href' => route('exam.addQuestion', $paper->uuid)];
            } elseif ($bank->count() < 60) {
                $shortPapers[] = ['label' => $label.': '.$bank->count().' questions; students will receive all available questions.', 'href' => $questionLink];
            }
            foreach ($bank as $question) {
                $problems = [];
                $text = preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', html_entity_decode(strip_tags($question->question_text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text === '' && ! preg_match('/<(img|svg|math)\b/i', $question->question_text)) {
                    $problems[] = 'empty question text';
                }
                if ($question->options_count < 2) {
                    $problems[] = 'fewer than two options';
                }
                if ($question->correct_count < 1) {
                    $problems[] = 'no correct answer';
                }
                if ($question->marks < 1) {
                    $problems[] = 'invalid marks';
                }
                $item = ['label' => $label.' · Question #'.$question->id, 'href' => route('exam.viewQuestion', $question->id)];
                if ($problems !== []) {
                    $invalidQuestions[] = ['label' => $item['label'].': '.implode(', ', $problems), 'href' => $item['href']];
                }
                if ($question->correct_count > 1) {
                    $multipleCorrect[] = $item;
                }
            }
            if (! $paper->start || ! $paper->end || $paper->start->gte($paper->end) || $paper->duration < 1 || ! $validDates
                || $paper->start->lt($exam->start_date->copy()->startOfDay()) || $paper->end->gt($exam->end_date->copy()->endOfDay())) {
                $schedules[] = ['label' => $label.': check admission times, duration and exam dates.', 'href' => $paperPage];
            }
            if ($paper->end && $paper->end->lte(now())) {
                $closed[] = ['label' => $label.': admission has closed.', 'href' => $paperPage];
            }
            foreach ($papers->slice($index + 1) as $other) {
                if ($paper->start && $paper->end && $other->start && $other->end && $paper->start->lt($other->end) && $other->start->lt($paper->end)) {
                    $overlaps[] = ['label' => $label.' overlaps '.$other->course->code.'.', 'href' => $paperPage];
                }
            }
        }
        $add('empty_papers', 'Question banks', 'blocker', 'Every course paper needs questions.', count($empty), $empty);
        $add('question_quality', 'Question quality and answer keys', 'blocker', 'Questions need content, positive marks, at least two options and a correct answer.', count($invalidQuestions), $invalidQuestions);
        $add('multiple_answers', 'Multiple correct answers', 'warning', 'Review these single-select questions: more than one option is marked correct.', count($multipleCorrect), $multipleCorrect);
        $add('question_count', 'Question allocation', 'warning', 'Real exams select up to 60 questions. Smaller banks work, but provide fewer questions.', count($shortPapers), $shortPapers);
        $add('paper_schedule', 'Admission windows and durations', 'blocker', 'Admission windows must be valid and within the exam dates. Each student’s timer may extend beyond admission closing.', count($schedules), $schedules);
        $add('paper_overlaps', 'Course schedule conflicts', 'blocker', 'Students cannot choose between course papers whose admission windows overlap.', count($overlaps), $overlaps);
        $otherExams = $validDates ? Exam::where('department_id', $exam->department_id)->where('set', $exam->set)->where('level', $exam->level)->where('id', '!=', $exam->id)
            ->whereDate('start_date', '<=', $exam->end_date)->whereDate('end_date', '>=', $exam->start_date)
            ->orderBy('start_date')->get(['uuid', 'title']) : collect();
        $add('exam_overlaps', 'Other exams for this student group', 'blocker', 'Overlapping exam dates for the same department, set and level can route students to the wrong exam.', $otherExams->count(), $otherExams->map(fn (Exam $other): array => ['label' => $other->title, 'href' => route('exam.edit', $other->uuid)])->all());
        $add('admission_closed', 'Admission still available', 'warning', 'Closed papers need a schedule review if you intend to admit students again.', count($closed), $closed);
        $cohort = Student::where('department_id', $exam->department_id)->where('set', $exam->set);
        $eligible = (clone $cohort)->where('level', $exam->level);
        $studentCount = (clone $eligible)->count();
        $studentPage = route('student', ['department' => $exam->department->uuid, 'set' => $exam->set]);
        $add('eligible_students', 'Eligible student records', 'blocker', 'Students must match this exam’s department, set and level.', $studentCount === 0 ? 1 : 0, [], $studentPage);
        $unassigned = (clone $cohort)->where(fn (Builder $query) => $query->whereNull('level')->orWhereNotIn('level', $levels->keys()->all()));
        $add('student_levels', 'Unassigned student levels', 'warning', 'Students in this department and set with a missing or invalid level may be excluded. Students with another valid level are not flagged.', (clone $unassigned)->count(), $this->studentItems($unassigned), $studentPage);
        $missingPhotos = (clone $eligible)->where(fn (Builder $query) => $query->whereNull('photo')->orWhere('photo', ''));
        $add('student_photos', 'Student passports', 'warning', 'Eligible students without an assigned passport. This checks saved photo paths, not image file availability.', (clone $missingPhotos)->count(), $this->studentItems($missingPhotos), route('student', ['department' => $exam->department->uuid, 'set' => $exam->set, 'level' => $exam->level]));
        $blockers = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'blocker'));
        $warnings = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'warning'));

        return Inertia::render('Dashboard/Exams/Readiness', [
            'exam' => [...$exam->only(['uuid', 'title', 'set', 'level']), 'department' => $exam->department->name, 'level_name' => $levels->get($exam->level, $exam->level)],
            'checks' => $checks,
            'summary' => ['status' => $blockers ? 'not_ready' : ($warnings ? 'review' : 'ready'), 'blockers' => $blockers, 'warnings' => $warnings, 'passed' => count($checks) - $blockers - $warnings, 'students' => $studentCount, 'papers' => $papers->count()],
            'checkedAt' => now()->toISOString(),
        ]);
    }

    /**
     * @param  Builder<Student>  $query
     * @return array<int, array{label: string, href: string}>
     */
    private function studentItems(Builder $query): array
    {
        return $query->orderBy('reg_no')->limit(20)->get(['uuid', 'reg_no', 'first_name', 'last_name'])
            ->map(fn (Student $student): array => ['label' => $student->reg_no.' · '.$student->first_name.' '.$student->last_name, 'href' => route('student.edit', $student->uuid)])->all();
    }
}
