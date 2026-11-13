<?php

namespace App\Observers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public const MODELS = [
        Exam::class => ['type' => 'exam', 'fields' => ['uuid', 'department_id', 'title', 'subtitle', 'set', 'level', 'start_date', 'end_date', 'status']],
        SingleExam::class => ['type' => 'paper', 'fields' => ['uuid', 'exam_id', 'course_id', 'start', 'end', 'duration']],
        Question::class => ['type' => 'question', 'fields' => ['single_exam_id', 'question_text', 'marks']],
        QuestionOption::class => ['type' => 'option', 'fields' => ['question_id', 'option_text', 'is_correct']],
        Report::class => ['type' => 'result', 'fields' => ['exam_id', 'course_id', 'student_id', 'first_ca', 'second_ca', 'exam', 'total_ca']],
        Student::class => ['type' => 'student', 'fields' => ['uuid', 'department_id', 'reg_no', 'set', 'level', 'first_name', 'middle_name', 'last_name', 'gender', 'dob', 'photo']],
        Department::class => ['type' => 'department', 'fields' => ['uuid', 'name']],
        Course::class => ['type' => 'course', 'fields' => ['uuid', 'name', 'code', 'unit']],
        User::class => ['type' => 'staff', 'fields' => ['name', 'email', 'status', 'is_admin']],
        Level::class => ['type' => 'level', 'fields' => ['code', 'name']],
    ];

    public function __construct(private AuditTrail $trail) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        if (! $this->trail->active()) {
            return;
        }
        $definition = self::MODELS[$model::class];
        $fields = $definition['fields'];
        if ($action === 'updated') {
            $fields = array_intersect($fields, array_keys($model->getChanges()));
        }
        $before = $action === 'created' ? [] : array_intersect_key($model->getRawOriginal(), array_flip($fields));
        $after = $action === 'deleted' ? [] : array_intersect_key($model->getAttributes(), array_flip($fields));
        $before = $this->normalize($before);
        $after = $this->normalize($after);
        if ($action === 'updated') {
            foreach (array_keys($after) as $field) {
                if (array_key_exists($field, $before) && $before[$field] === $after[$field]) {
                    unset($before[$field], $after[$field]);
                }
            }
        }
        $metadata = [];
        if ($model instanceof User && $action !== 'deleted' && ($action === 'created' || array_key_exists('password', $model->getChanges()))) {
            $metadata['password_changed'] = true;
        }
        if ($action === 'updated' && $before === [] && $after === [] && $metadata === []) {
            return;
        }
        if ($action === 'deleted' && ! $model instanceof User && ! $model instanceof Level) {
            $metadata['note'] = 'Related records removed by database cascading are covered by this parent deletion.';
        }
        $this->trail->record($model, $definition['type'], $action, $before, $after, $metadata);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        foreach ($values as $field => $value) {
            if ($value === null) {
                continue;
            }
            if (in_array($field, ['is_admin', 'is_correct'], true)) {
                $values[$field] = (bool) $value;
            } elseif (str_ends_with($field, '_id') || in_array($field, ['marks', 'duration', 'first_ca', 'second_ca', 'exam', 'total_ca', 'unit'], true)) {
                $values[$field] = (int) $value;
            }
        }

        return $values;
    }
}
