<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Report;
use App\Models\Student;
use App\Services\StudentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it calculates the correct grade based on the score', function (int $firstCa, int $secondCa, int $examScore, string $expectedGrade) {
    $student = Student::factory()->create();
    $course = Course::factory()->create();
    $exam = Exam::factory()->create();

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
    [10, 10, 30, 'D'], // 50
    [10, 10, 29, 'F'], // 49
    [0, 0, 0, 'F'],    // 0
]);
