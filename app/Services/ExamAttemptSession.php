<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class ExamAttemptSession
{
    public const FINAL_DELIVERY_GRACE_SECONDS = 30;

    public function authorize(Request $request, Attempt $attempt, bool $requireToken = false): void
    {
        if (! $requireToken && $attempt->access_token_hash === null) {
            return;
        }
        $token = $request->bearerToken();
        if ($token === null || $attempt->access_token_hash === null || ! hash_equals($attempt->access_token_hash, hash('sha256', $token))) {
            $this->fail('INVALID_ATTEMPT_TOKEN', 'A valid attempt token is required.', 401);
        }
    }

    /** The caller holds the attempt lock. */
    public function start(Attempt $attempt): void
    {
        if ($attempt->question_order !== null) {
            return;
        }
        if ($attempt->started_at !== null) {
            $this->fail('LEGACY_ATTEMPT_NOT_RESUMABLE', 'This older attempt has no saved paper allocation. Contact examination staff.');
        }
        $paper = $attempt->singleExam;
        if ((int) $paper->duration < 1) {
            $this->fail('INVALID_EXAM_DURATION', 'The examination duration must be configured by staff.');
        }
        $questions = Question::where('single_exam_id', $paper->id)
            ->with(['options' => fn ($query) => $query->inRandomOrder()])
            ->inRandomOrder()->limit(60)->get();
        if ($questions->isEmpty()) {
            $this->fail('EXAM_HAS_NO_QUESTIONS', 'This examination has no questions. Contact examination staff.');
        }
        $started = now()->startOfSecond();
        $attempt->question_order = $questions->map(fn (Question $question): array => [
            'id' => $question->id,
            'option_ids' => array_map('intval', $question->options->pluck('id')->all()),
        ])->all();
        $attempt->started_at = $started;
        $attempt->expires_at = $started->copy()->addMinutes((int) $paper->duration);
        $attempt->save();
    }

    public function questions(Attempt $attempt): array
    {
        $order = $attempt->question_order ?? [];
        $questions = Question::where('single_exam_id', $attempt->single_exam_id)
            ->whereIn('id', array_column($order, 'id'))->with('options')->get()->keyBy('id');
        $result = [];
        foreach ($order as $allocated) {
            $question = $questions->get($allocated['id']);
            if (! $question) {
                $this->fail('EXAM_PAPER_CHANGED', 'An allocated question is missing. Contact examination staff.');
            }
            $options = $question->options->keyBy('id');
            $orderedOptions = [];
            foreach ($allocated['option_ids'] as $id) {
                $option = $options->get($id);
                if (! $option) {
                    $this->fail('EXAM_PAPER_CHANGED', 'An allocated option is missing. Contact examination staff.');
                }
                $orderedOptions[] = $option->makeHidden('is_correct')->toArray();
            }
            $data = $question->toArray();
            $data['options'] = $orderedOptions;
            $result[] = $data;
        }

        return $result;
    }

    public function timing(Attempt $attempt): array
    {
        $remaining = $attempt->expires_at === null ? null : max(0, (int) floor(now()->diffInSeconds($attempt->expires_at, false)));

        return [
            'server_now' => now()->toISOString(),
            'started_at' => $attempt->started_at?->toISOString(),
            'expires_at' => $attempt->expires_at?->toISOString(),
            'remaining_seconds' => $attempt->status === 'completed' ? 0 : $remaining,
            'final_delivery_grace_seconds' => self::FINAL_DELIVERY_GRACE_SECONDS,
            'final_delivery_deadline' => $attempt->expires_at?->copy()->addSeconds(self::FINAL_DELIVERY_GRACE_SECONDS)->toISOString(),
        ];
    }

    public function answers(Attempt $attempt): object
    {
        return (object) Answer::where('attempt_id', $attempt->id)->pluck('option_id', 'question_id')->all();
    }

    public function unchanged(Attempt $attempt, array $answers): bool
    {
        $saved = Answer::where('attempt_id', $attempt->id)->pluck('option_id', 'question_id');
        foreach ($answers as $id => $option) {
            if (! ctype_digit((string) $id) || (int) $id < 1 || (string) (int) $id !== (string) $id) {
                return false;
            }
            $value = $saved->get($id);
            if (($option === null ? null : (int) $option) !== ($value === null ? null : (int) $value)) {
                return false;
            }
        }

        return true;
    }

    public function payloadHash(array $answers, bool $savedOnly): string
    {
        $normalized = [];
        foreach ($answers as $id => $option) {
            $normalized[(string) $id] = $option === null ? null : (int) $option;
        }
        ksort($normalized, SORT_NUMERIC);

        return hash('sha256', json_encode(['saved_only' => $savedOnly, 'answers' => (object) $normalized], JSON_THROW_ON_ERROR));
    }

    public function receipt(Attempt $attempt): array
    {
        return [
            'success' => true,
            'status' => 'Submitted successfully',
            'attempt_id' => $attempt->id,
            'attempt_status' => $attempt->status,
            'submission_id' => $attempt->submission_id,
            'submitted_at' => $attempt->submitted_at?->toISOString(),
        ];
    }

    public function requireStarted(Attempt $attempt): void
    {
        if ($attempt->access_token_hash !== null && $attempt->expires_at === null) {
            $this->fail('ATTEMPT_NOT_STARTED', 'Load the examination paper before saving or submitting.');
        }
    }

    public function fail(string $code, string $message, int $status = 409): never
    {
        throw new HttpResponseException(response()->json(['success' => false, 'code' => $code, 'message' => $message], $status));
    }
}
