<?php

use App\Models\Department;
use App\Models\Exam;
use App\Models\Level;
use App\Models\SingleExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function sharedLevelDepartment(): Department
{
    return Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
}

function sharedLevelExamInput(Department $department): array
{
    return ['department_id' => $department->id, 'title' => 'Programme examination', 'set' => '2025/2026', 'level' => 'nd', 'start_date' => '2026-11-12', 'end_date' => '2026-11-13'];
}

test('ND and HND are installed once as the shared selectable levels', function () {
    expect(Level::orderBy('id')->pluck('code')->all())->toBe(['nd', 'hnd']);
    expect(Level::orderBy('id')->pluck('name')->all())->toBe(['ND', 'HND']);
});

test('student and exam pages all use the same database level options', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = sharedLevelDepartment();
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'TEST/001', 'first_name' => 'Ada', 'last_name' => 'Example', 'gender' => 'F', 'set' => '2025/2026', 'level' => 'nd']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), ...sharedLevelExamInput($department)]);
    foreach (['/dashboard/student', '/dashboard/student/department/'.$department->uuid.'/2025-2026/nd/add', '/dashboard/student/'.$student->uuid.'/edit', '/dashboard/exam', '/dashboard/exam/add', '/dashboard/exam/'.$exam->uuid.'/edit'] as $url) {
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('levels', ['nd', 'hnd']));
    }
});

test('students can be added and edited using either shared level', function (string $level) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = sharedLevelDepartment();
    $input = ['department_id' => $department->id, 'first_name' => 'Ada', 'last_name' => 'Example', 'reg_no' => 'TEST/001', 'gender' => 'F', 'set' => '2025/2026', 'level' => $level];
    $this->actingAs($admin)->post('/dashboard/student/add', $input)->assertRedirect()->assertSessionHasNoErrors();
    $student = Student::where('reg_no', 'TEST/001')->firstOrFail();
    expect($student->level)->toBe($level);
    $other = $level === 'nd' ? 'hnd' : 'nd';
    $this->post('/dashboard/student/edit', [...$input, 'uuid' => $student->uuid, 'level' => $other])->assertRedirect()->assertSessionHasNoErrors();
    expect($student->fresh()->level)->toBe($other);
})->with(['nd', 'hnd']);

test('exams can be added and edited using either shared level', function (string $level) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = sharedLevelDepartment();
    $input = [...sharedLevelExamInput($department), 'level' => $level];
    $this->actingAs($admin)->post('/dashboard/exam/add', $input)->assertRedirectToRoute('exam')->assertSessionHasNoErrors();
    $exam = Exam::firstOrFail();
    expect($exam->level)->toBe($level);
    $other = $level === 'nd' ? 'hnd' : 'nd';
    $this->post('/dashboard/exam/edit', [...$input, 'id' => $exam->id, 'level' => $other])->assertRedirect()->assertSessionHasNoErrors();
    expect($exam->fresh()->level)->toBe($other);
})->with(['nd', 'hnd']);

test('unknown and year-specific levels cannot be saved through either form', function (mixed $level) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $department = sharedLevelDepartment();
    $this->actingAs($admin)->post('/dashboard/student/add', ['first_name' => 'Ada', 'last_name' => 'Example', 'reg_no' => 'TEST/001', 'gender' => 'F', 'department_id' => $department->id, 'set' => '2025/2026', 'level' => $level])->assertSessionHasErrors('level');
    $this->assertDatabaseCount('students', 0);
    $this->post('/dashboard/exam/add', [...sharedLevelExamInput($department), 'level' => $level])->assertSessionHasErrors('level');
    $this->assertDatabaseCount('exams', 0);
})->with(['nd1', 'hnd2', 'unknown', '', [['bad']]]);

test('existing students and exams normalize to the shared programme without losing records', function () {
    $department = sharedLevelDepartment();
    $students = collect(['ND', 'nd1', 'nd2', 'HND', 'hnd1', 'hnd2'])->map(fn ($level, $index) => Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'LEGACY/'.$index, 'first_name' => 'Ada', 'last_name' => 'Example', 'gender' => 'F', 'set' => '2025/2026', 'level' => $level]));
    $exams = collect(['ND', 'nd1', 'nd2', 'HND', 'hnd1', 'hnd2'])->map(fn ($level) => Exam::create(['uuid' => (string) str()->uuid(), ...sharedLevelExamInput($department), 'level' => $level]));
    $migration = require database_path('migrations/2026_11_12_101207_create_levels_table.php');
    $migration->down();
    $migration->up();
    expect($students->map(fn ($student) => $student->fresh()->level)->all())->toBe(['nd', 'nd', 'nd', 'hnd', 'hnd', 'hnd']);
    expect($exams->map(fn ($exam) => $exam->fresh()->level)->all())->toBe(['nd', 'nd', 'nd', 'hnd', 'hnd', 'hnd']);
    $this->assertDatabaseCount('students', 6);
    $this->assertDatabaseCount('exams', 6);
    $this->assertDatabaseCount('levels', 2);
});

test('a legacy ND student can enter an ND1 exam after shared level normalization', function () {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 5));
    $department = sharedLevelDepartment();
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'STD/TEST/003', 'first_name' => 'Ada', 'last_name' => 'Example', 'gender' => 'F', 'set' => '2025/2026', 'level' => 'nd']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), ...sharedLevelExamInput($department), 'level' => 'nd1']);
    $course = \App\Models\Course::create(['uuid' => (string) str()->uuid(), 'name' => 'Test Course', 'code' => 'TST101']);
    $paper = SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'start' => '2026-11-12 10:00:00', 'end' => '2026-11-12 11:00:00', 'duration' => 40]);
    $migration = require database_path('migrations/2026_11_12_101207_create_levels_table.php');
    $migration->down();
    $migration->up();
    Event::fake();
    $this->postJson('/api/student-login', ['reg_no' => $student->reg_no])->assertOk()->assertJsonPath('type', 'start');
    $this->assertDatabaseHas('attempts', ['student_id' => $student->id, 'single_exam_id' => $paper->id]);
});
