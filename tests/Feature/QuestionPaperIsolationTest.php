<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\SingleExam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function questionPaperFixture(): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Test department']);
    $course = Course::create(['uuid' => (string) str()->uuid(), 'name' => 'Accounting', 'code' => 'ACC101']);
    $papers = [];
    foreach (['First Semester', 'Second Semester'] as $title) {
        $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => $title, 'set' => '2025/2026', 'level' => 'nd', 'start_date' => '2026-11-12', 'end_date' => '2026-11-13']);
        $papers[] = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'duration' => 40, 'start' => '2026-11-12 10:00:00', 'end' => '2026-11-12 11:00:00']);
    }
    Question::create(['single_exam_id' => $papers[0]->id, 'question_text' => 'Original first-semester question', 'marks' => 1]);

    return $papers;
}

function questionPaperInput(SingleExam $paper): array
{
    return ['single_exam_id' => $paper->id, 'question_text' => 'Second-semester question', 'points' => 2, 'options' => [['option_text' => 'Correct answer', 'is_correct' => true], ['option_text' => 'Other answer', 'is_correct' => false]]];
}

test('question editor retains the requested paper even when another paper exists first', function () {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->get('/dashboard/exam/'.$second->uuid.'/add-question')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Exams/Single/AddQuestion')->where('singleExam.id', $second->id)
        ->where('singleExam.uuid', $second->uuid)->where('singleExam.exam.title', 'Second Semester')->where('singleExam.course.code', 'ACC101'));
    $this->get('/dashboard/exam/'.$second->uuid.'/single-exam')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('singleExam.id', $second->id)->has('questions.data', 0));
});

test('saving repeatedly stays with the chosen paper and its question bank', function () {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    for ($i = 1; $i <= 2; $i++) {
        $response = $this->actingAs($admin)->post('/dashboard/exam/'.$second->uuid.'/add-question', [...questionPaperInput($second), 'question_text' => 'New question '.$i])
            ->assertRedirectToRoute('exam.addQuestion', $second->uuid)->assertSessionHasNoErrors();
        $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('singleExam.id', $second->id));
    }
    expect($first->questions()->count())->toBe(1);
    expect($second->questions()->count())->toBe(2);
    $question = $second->questions()->where('question_text', 'New question 1')->firstOrFail();
    expect($question->marks)->toBe(2);
    expect($question->options()->count())->toBe(2);
    expect($question->options()->where('is_correct', true)->firstOrFail()->option_text)->toBe('Correct answer');
    $this->get('/dashboard/exam/'.$second->uuid.'/single-exam')->assertInertia(fn (AssertableInertia $page) => $page->has('questions.data', 2));
});

test('a different paper in the submitted form is rejected instead of silently misfiling questions', function () {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->postJson('/dashboard/exam/'.$second->uuid.'/add-question', questionPaperInput($first))
        ->assertUnprocessable()->assertJsonValidationErrors('single_exam_id');
    expect($first->questions()->count())->toBe(1);
    expect($second->questions()->count())->toBe(0);
});

test('invalid paper identifiers do not create questions', function (mixed $id) {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->postJson('/dashboard/exam/'.$second->uuid.'/add-question', [...questionPaperInput($second), 'single_exam_id' => $id])
        ->assertUnprocessable()->assertJsonValidationErrors('single_exam_id');
    $this->assertDatabaseCount('questions', 1);
})->with([null, 9999, [['invalid']]]);

test('unknown editor URLs never fall back to the first paper', function () {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->get('/dashboard/exam/unknown/add-question')->assertNotFound();
    $this->post('/dashboard/exam/unknown/add-question', questionPaperInput($second))->assertNotFound();
    $this->assertDatabaseCount('questions', 1);
});

test('the existing question creation endpoint still saves to its explicit valid paper', function () {
    [$first, $second] = questionPaperFixture();
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->post('/dashboard/exam/add-question', questionPaperInput($second))->assertRedirectToRoute('exam.addQuestion', $second->uuid);
    expect($second->questions()->count())->toBe(1);
    expect($first->questions()->count())->toBe(1);
});

test('question authoring remains restricted to administrators', function () {
    [$first, $second] = questionPaperFixture();
    $this->get('/dashboard/exam/'.$second->uuid.'/add-question')->assertRedirect('/login');
    $staff = User::factory()->create(['is_admin' => false, 'status' => 'active']);
    $this->actingAs($staff)->get('/dashboard/exam/'.$second->uuid.'/add-question')->assertRedirect('/dashboard');
    $this->post('/dashboard/exam/'.$second->uuid.'/add-question', questionPaperInput($second))->assertRedirect('/dashboard');
    $this->assertDatabaseCount('questions', 1);
});
