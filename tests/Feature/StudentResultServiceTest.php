<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Report;
use App\Models\Student;
use App\Services\StudentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it calculates the correct grade based on the score', function (int $firstCa, int $secondCa, int $examScore, string $expectedGrade) {
    $department = Department::create(['name' => 'Test Dept', 'uuid' => str()->uuid()]);
    $student = Student::forceCreate([
        'uuid' => str()->uuid(),
        'department_id' => $department->id,
        'reg_no' => 'REG/001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'set' => '2024',
        'gender' => 'Male',
        'level' => '100',
    ]);
    $course = Course::forceCreate([
        'uuid' => str()->uuid(),
        'name' => 'Test Course',
        'code' => 'TEST101',
    ]);
    $exam = Exam::forceCreate([
        'uuid' => str()->uuid(),
        'department_id' => $department->id,
        'title' => 'Test Exam',
        'set' => '2024',
        'level' => '100',
        'start_date' => now(),
        'end_date' => now()->addDays(7),
    ]);

    Report::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'exam_id' => $exam->id,
        'first_ca' => $firstCa,
        'second_ca' => $secondCa,
        'exam' => $examScore,
    ]);

    $grade = StudentResultService::courseGrade($student, $course, $exam);

    expect($grade)->toBe($expectedGrade);
})->with([
    [20, 20, 40, 'A'], // 80
    [20, 20, 30, 'A'], // 70
    [15, 15, 35, 'B'], // 65
    [15, 15, 34, 'C'], // 64
    [15, 15, 30, 'C'], // 60
    [10, 10, 35, 'D'], // 55
    [10, 10, 30, 'E'], // 50
    [10, 10, 29, 'F'], // 49
    [0, 0, 0, 'F'],    // 0
]);

test('it calculates the correct course remark', function () {
    $department = Department::create(['name' => 'Test Dept', 'uuid' => str()->uuid()]);
    $student = Student::forceCreate([
        'uuid' => str()->uuid(),
        'department_id' => $department->id,
        'reg_no' => 'REG/001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'set' => '2024',
        'gender' => 'Male',
        'level' => '100',
    ]);
    $exam = Exam::forceCreate([
        'uuid' => str()->uuid(),
        'department_id' => $department->id,
        'title' => 'Test Exam',
        'set' => '2024',
        'level' => '100',
        'start_date' => now(),
        'end_date' => now()->addDays(7),
    ]);
    $course1 = Course::forceCreate([
        'uuid' => str()->uuid(),
        'name' => 'Course 1',
        'code' => 'C1',
    ]);
    $course2 = Course::forceCreate([
        'uuid' => str()->uuid(),
        'name' => 'Course 2',
        'code' => 'C2',
    ]);
    $course3 = Course::forceCreate([
        'uuid' => str()->uuid(),
        'name' => 'Course 3',
        'code' => 'C3',
    ]);

    // Course 1: Passed (70)
    Report::create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'exam_id' => $exam->id,
        'first_ca' => 20,
        'second_ca' => 20,
        'exam' => 30,
    ]);

    // Initially all passed
    expect(StudentResultService::courseRemark($student, $exam))->toBe('passed');

    // Course 2: Failed (49)
    Report::create([
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'exam_id' => $exam->id,
        'first_ca' => 10,
        'second_ca' => 10,
        'exam' => 29,
    ]);

    expect(StudentResultService::courseRemark($student, $exam))->toBe('C/O (1)');

    // Course 3: Failed (40)
    Report::create([
        'student_id' => $student->id,
        'course_id' => $course3->id,
        'exam_id' => $exam->id,
        'first_ca' => 10,
        'second_ca' => 10,
        'exam' => 20,
    ]);

    expect(StudentResultService::courseRemark($student, $exam))->toBe('C/O (2)');
});
