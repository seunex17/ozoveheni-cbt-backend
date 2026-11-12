<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Report;
use App\Models\SingleExam;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function resultSlipFixture(int $count = 8): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'OCHST/ND/25/0147', 'first_name' => 'Amina', 'middle_name' => 'Grace', 'last_name' => 'Adebayo', 'set' => '2025/2026', 'level' => 'nd2', 'gender' => 'F']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'First Semester Examination', 'subtitle' => '2026/2027', 'set' => $student->set, 'level' => 'nd1', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20']);
    $rows = [
        ['ANA101', 'Human Anatomy', 16, 15, 48, 3], ['PHY101', 'Human Physiology', 14, 14, 39, 3],
        ['CHT101', 'Introduction to Community Health', 17, 16, 44, 2], ['MCB101', 'General Microbiology', 12, 14, 36, 2],
        ['NUT101', 'Nutrition and Dietetics', 15, 17, 40, 2], ['FAD101', 'First Aid and Emergency Care', 16, 15, 36, 2],
        ['PSY101', 'Introduction to Psychology', 12, 14, 37, 2], ['COM101', 'Communication Skills', 17, 16, 44, 2],
    ];
    for ($i = 0; $i < $count; $i++) {
        [$code, $name, $first, $second, $score, $units] = $rows[$i % 8];
        if ($i >= 8) {
            $code = 'EXT'.$i;
            $name = 'Extended Course '.$i.' in Community Health Administration and Practical Clinical Communication';
        }
        $course = Course::create(['uuid' => (string) str()->uuid(), 'code' => $code, 'name' => $name, 'unit' => $units]);
        SingleExam::create(['uuid' => (string) str()->uuid(), 'exam_id' => $exam->id, 'course_id' => $course->id, 'start' => '2026-11-10 08:00:00', 'end' => '2026-11-10 12:00:00', 'duration' => 40]);
        Report::create(['exam_id' => $exam->id, 'course_id' => $course->id, 'student_id' => $student->id, 'first_ca' => $first, 'second_ca' => $second, 'exam' => $score]);
    }
    $exam->load('department');
    $singleExams = $exam->singleExams()->with('course')->get();

    return compact('exam', 'student', 'singleExams');
}

test('approved result slip uses live identity scores and existing grade calculations', function () {
    $fixture = resultSlipFixture();
    $html = view('student-result-slip', $fixture)->render();
    expect($html)->toContain('Adebayo Grace Amina', 'OCHST/ND/25/0147', 'Community Health', 'ND1', '2026/2027', '3.64', 'Passed');
    expect($html)->toContain('Human Anatomy', 'ANA101', '<td class="num total">79</td>', '>A</td>');
    expect($html)->not->toContain('DESIGN PROPOSAL', 'SAMPLE ONLY', 'Demonstration student', 'tailwind.js', 'daisy.css');
    expect($html)->toContain('NO PASSPORT', 'data:image/jpeg;base64,', 'Official stamp');
    if ($previewPath = getenv('RESULT_SLIP_PREVIEW_DIR')) {
        file_put_contents($previewPath.'/student-result-slip-live.html', $html);
    }
});

test('result slip embeds available student passports and falls back for missing files', function () {
    $fixture = resultSlipFixture(0);
    Storage::fake('public');
    Storage::disk('public')->put('students/passport.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='));
    $fixture['student']->photo = 'students/passport.png';
    $html = view('student-result-slip', $fixture)->render();
    expect($html)->toContain('data:image/png;base64,', 'alt="Student passport"')->not->toContain('NO PASSPORT');
    $fixture['student']->photo = 'students/missing.jpg';
    expect(view('student-result-slip', $fixture)->render())->toContain('NO PASSPORT');
});

test('result slip preserves carry-over remarks and zero scores', function () {
    $fixture = resultSlipFixture(1);
    Report::query()->update(['first_ca' => 0, 'second_ca' => 0, 'exam' => 0]);
    $html = view('student-result-slip', $fixture)->render();
    expect($html)->toContain('C/O (1)', '0.00', '<td class="num total">0</td>', '>F</td>')->not->toContain('No outstanding courses');
});

test('result slip supports empty papers and escapes student and course text', function () {
    $fixture = resultSlipFixture(0);
    $fixture['student']->first_name = '<script>alert(1)</script>';
    $html = view('student-result-slip', $fixture)->render();
    expect($html)->toContain('No course papers are available', '0.00', '&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>');
});

test('result slip includes every paper for long course lists', function () {
    $fixture = resultSlipFixture(32);
    $html = view('student-result-slip', $fixture)->render();
    expect($html)->toContain('EXT31', 'Extended Course 31')->not->toContain('Page 1 of 1');
    expect(substr_count($html, '<td class="code">'))->toBe(32);
    if ($previewPath = getenv('RESULT_SLIP_PREVIEW_DIR')) {
        file_put_contents($previewPath.'/student-result-slip-long.html', $html);
    }
});

test('result slip retains all courses and scores in compact single-sheet layouts', function (int $count) {
    $fixture = resultSlipFixture($count);
    $html = view('student-result-slip', $fixture)->render();
    expect(substr_count($html, '<td class="code">'))->toBe($count);
    expect($html)->toContain('EXT'.($count - 1), 'CGPA', 'Passed', 'Academic Division');
    if ($previewPath = getenv('RESULT_SLIP_PREVIEW_DIR')) {
        file_put_contents($previewPath.'/student-result-slip-'.$count.'.html', $html);
    }
})->with([12, 16, 20]);
