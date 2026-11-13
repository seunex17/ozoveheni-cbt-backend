<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditTrail
{
    private ?int $actorId = null;

    private string $actorName = '';

    /** @var array<string, string> */
    private array $labels = [];

    public function begin(User $actor): void
    {
        $this->actorId = (int) $actor->id;
        $this->actorName = $actor->name;
        $this->labels = [];
    }

    public function end(): void
    {
        $this->actorId = null;
        $this->actorName = '';
        $this->labels = [];
    }

    public function active(): bool
    {
        return $this->actorId !== null;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(Model $subject, string $type, string $action, array $before = [], array $after = [], array $metadata = []): void
    {
        if (! $this->active()) {
            return;
        }
        AuditLog::create([
            'actor_id' => $this->actorId, 'actor_name' => $this->actorName,
            'action' => $action, 'subject_type' => $type, 'subject_id' => $subject->getKey(),
            'subject_label' => Str::limit($this->label($subject).(isset($metadata['student']) ? ' · '.$metadata['student'] : ''), 500),
            'before_values' => $before, 'after_values' => $after, 'metadata' => $metadata,
        ]);
    }

    private function relatedLabel(string $class, int $id): string
    {
        $key = $class.':'.$id;
        if (! isset($this->labels[$key])) {
            $model = $class::find($id);
            $this->labels[$key] = $model ? $this->label($model) : '#'.$id;
        }

        return $this->labels[$key];
    }

    private function label(Model $model): string
    {
        return match (true) {
            $model instanceof Exam => $model->title.' · '.$model->set.' · '.$model->level,
            $model instanceof Student => $model->reg_no.' · '.$model->first_name.' '.$model->last_name,
            $model instanceof Course => $model->code.' · '.$model->name,
            $model instanceof SingleExam => $this->relatedLabel(Exam::class, (int) $model->exam_id).' · '.$this->relatedLabel(Course::class, (int) $model->course_id),
            $model instanceof Question => 'Question #'.$model->id.' · '.$this->relatedLabel(SingleExam::class, (int) $model->single_exam_id),
            $model instanceof QuestionOption => 'Option #'.$model->id.' · '.$this->relatedLabel(Question::class, (int) $model->question_id),
            $model instanceof Report => $this->relatedLabel(Student::class, (int) $model->student_id).' · '.$this->relatedLabel(Course::class, (int) $model->course_id).' · '.$this->relatedLabel(Exam::class, (int) $model->exam_id),
            default => (string) ($model->getAttribute('name') ?? '#'.$model->getKey()),
        };
    }
}
