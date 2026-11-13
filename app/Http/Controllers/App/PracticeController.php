<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use App\Models\SingleExam;
use App\Models\Student;
use App\Services\ExamAttemptSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use JsonException;

class PracticeController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $input = $request->validate(['reg_no' => ['required', 'string', 'max:255']]);
        $student = Student::with('department:id,name')->where('reg_no', trim($input['reg_no']))->first();
        if (! $student) {
            $this->fail('STUDENT_NOT_FOUND', 'Student does not exist.', 404);
        }
        $level = $this->requireLevel($student);
        $expires = now()->addHours(8);
        $token = Crypt::encryptString(json_encode([
            'purpose' => 'practice', 'student_id' => $student->id, 'expires_at' => $expires->timestamp,
        ], JSON_THROW_ON_ERROR));
        $photo = $student->photo ? (config('filesystems.disks.public.driver') === 'local'
            ? url('/storage/'.ltrim($student->photo, '/')) : Storage::disk('public')->url($student->photo)) : null;

        return $this->respond([
            'success' => true, 'mode' => 'practice', 'practice_token' => $token, 'token_type' => 'Bearer',
            'expires_at' => $expires->toISOString(),
            'student' => [...$student->only(['id', 'uuid', 'reg_no', 'first_name', 'middle_name', 'last_name', 'level', 'department_id']),
                'level_name' => $level->name, 'department' => $student->department?->only(['id', 'name']), 'photo_url' => $photo],
            'sessions' => $this->availableSessions($student),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return $this->respond(['success' => true, 'sessions' => $this->availableSessions($this->student($request))]);
    }

    public function exams(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $input = $request->validate(['session' => ['required', 'string', 'max:100']]);
        $exams = $this->availableExams($student)->where('set', $input['session'])
            ->orderBy('title')->orderBy('id')->get(['id', 'uuid', 'title', 'subtitle', 'set', 'level', 'start_date', 'end_date']);

        return $this->respond(['success' => true, 'exams' => $exams]);
    }

    public function courses(Request $request, string $exam): JsonResponse
    {
        $student = $this->student($request);
        $selected = $this->availableExams($student)->where('uuid', $exam)->first();
        if (! $selected) {
            $this->unavailable();
        }
        $papers = SingleExam::where('exam_id', $selected->id)->whereHas('questions')
            ->with('course:id,uuid,name,code')->withCount('questions')->orderBy('id')
            ->get(['id', 'uuid', 'course_id', 'duration']);

        return $this->respond(['success' => true, 'exam' => $selected->only(['uuid', 'title', 'subtitle', 'set', 'level']), 'papers' => $papers]);
    }

    public function questions(Request $request, string $paper): JsonResponse
    {
        $student = $this->student($request);
        $selected = SingleExam::where('uuid', $paper)
            ->whereIn('exam_id', $this->availableExams($student)->select('id'))
            ->with(['course:id,uuid,name,code', 'exam:id,uuid,title,subtitle,set,level'])->first();
        if (! $selected) {
            $this->unavailable();
        }
        $questions = Question::where('single_exam_id', $selected->id)->orderBy('id')
            ->with(['options' => fn ($query) => $query->select(['id', 'question_id', 'option_text', 'is_correct'])->orderBy('id')])
            ->get(['id', 'single_exam_id', 'question_text', 'marks']);
        if ($questions->isEmpty()) {
            $this->unavailable();
        }

        return $this->respond([
            'success' => true, 'mode' => 'practice', 'paper' => $selected->only(['uuid', 'duration', 'course', 'exam']),
            'questions' => $questions, 'question_count' => $questions->count(), 'total_marks' => (int) $questions->sum('marks'),
            'scoring' => 'client', 'server_time' => now()->toISOString(),
        ]);
    }

    /** @return Builder<Exam> */
    private function availableExams(Student $student): Builder
    {
        return Exam::where('department_id', $student->department_id)->where('level', $student->level)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function (Builder $query): void {
                $query->whereDate('end_date', '<', now()->toDateString())->orWhere('status', 'completed');
            })
            ->whereHas('singleExams.questions')
            ->whereDoesntHave('singleExams', function (Builder $query): void {
                $query->where('start', '>', now())->orWhere('end', '>', now());
            })
            ->whereDoesntHave('singleExams.attempts', function (Builder $query): void {
                $query->where('status', 'in_progress')->whereNull('submitted_at')
                    ->where(function (Builder $timer): void {
                        $timer->whereNull('expires_at')->orWhere('expires_at', '>=', now()->subSeconds(ExamAttemptSession::FINAL_DELIVERY_GRACE_SECONDS));
                    });
            });
    }

    /** @return array<int, string> */
    private function availableSessions(Student $student): array
    {
        return $this->availableExams($student)->select('set')->distinct()->orderByDesc('set')->pluck('set')->all();
    }

    private function student(Request $request): Student
    {
        try {
            $payload = json_decode(Crypt::decryptString($request->bearerToken() ?? ''), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->fail('INVALID_PRACTICE_TOKEN', 'Sign in to practice mode again.', 401);
        }
        if (! is_array($payload) || ($payload['purpose'] ?? '') !== 'practice' || ! is_int($payload['student_id'] ?? null) || ! is_int($payload['expires_at'] ?? null)) {
            $this->fail('INVALID_PRACTICE_TOKEN', 'Sign in to practice mode again.', 401);
        }
        if ($payload['expires_at'] <= now()->timestamp) {
            $this->fail('PRACTICE_TOKEN_EXPIRED', 'Your practice session has expired. Sign in again.', 401);
        }
        $student = Student::find($payload['student_id']);
        if (! $student) {
            $this->fail('INVALID_PRACTICE_TOKEN', 'Sign in to practice mode again.', 401);
        }
        $this->requireLevel($student);

        return $student;
    }

    private function requireLevel(Student $student): Level
    {
        $level = Level::where('code', $student->level)->first();
        if (! $level) {
            $this->fail('PRACTICE_LEVEL_UNAVAILABLE', 'Ask staff to assign a valid level before practising.', 403);
        }

        return $level;
    }

    private function unavailable(): never
    {
        $this->fail('PRACTICE_EXAM_UNAVAILABLE', 'This past examination is not available for practice.', 404);
    }

    private function fail(string $code, string $message, int $status): never
    {
        throw new HttpResponseException($this->respond(['success' => false, 'code' => $code, 'message' => $message], $status));
    }

    private function respond(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)->header('Cache-Control', 'no-store, private');
    }
}
