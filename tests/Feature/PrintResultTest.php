<?php

use App\Models\Department;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function printResultFixture(): array
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);
    $exam = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'title' => 'Semester exam', 'set' => '2025/2026', 'level' => 'nd1', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20']);
    $student = Student::forceCreate(['uuid' => (string) str()->uuid(), 'department_id' => $department->id, 'reg_no' => 'CH/001', 'first_name' => 'Ada', 'last_name' => 'Okafor', 'gender' => 'F', 'set' => '2025/2026', 'level' => 'nd2']);
    $voucher = Voucher::create(['exam_id' => $exam->id, 'pin' => '123456789012', 'serial' => 'SN-TEST', 'reference' => (string) str()->uuid()]);

    return compact('exam', 'student', 'voucher', 'department');
}

test('result lookup requires an active authenticated account and permits staff', function () {
    $this->get('/dashboard/print/result')->assertRedirect('/login');
    $this->post('/dashboard/print/result', [])->assertRedirect('/login');
    $inactive = User::factory()->create(['status' => 'inactive']);
    $this->actingAs($inactive)->get('/dashboard/print/result')->assertRedirect('/');
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/print/result')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Print/Result')->has('exams', 0));
});

test('result lookup supplies clear exam identity without voucher data', function () {
    $fixture = printResultFixture();
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $this->actingAs($staff)->get('/dashboard/print/result')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('exams.0.id', $fixture['exam']->id)->where('exams.0.department.name', 'Community Health')
        ->where('exams.0.set', '2025/2026')->where('exams.0.level', 'nd1')->missing('vouchers'));
});

test('a valid voucher opens the selected result once and supplies student identity', function () {
    ['exam' => $exam, 'student' => $student, 'voucher' => $voucher] = printResultFixture();
    $staff = User::factory()->create(['status' => 'active', 'is_admin' => false]);
    $input = ['exam_id' => $exam->id, 'reg_no' => $student->reg_no, 'pin' => $voucher->pin];
    $this->actingAs($staff)->post('/dashboard/print/result', $input)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/Print/ResultGenerate')->where('student.reg_no', 'CH/001')->where('student.first_name', 'Ada')
        ->where('pdfUrl', route('studentResultSlip', ['exam' => $exam->uuid, 'sid' => $student->id]))
        ->missing('pin')->missing('student.dob')->missing('student.photo'));
    $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    $this->from('/dashboard/print/result')->post('/dashboard/print/result', $input)->assertRedirect('/dashboard/print/result')->assertSessionHasErrors('pin');
});

test('student lookup failures do not consume vouchers', function (string $mismatch) {
    ['exam' => $exam, 'student' => $student, 'voucher' => $voucher] = printResultFixture();
    if ($mismatch === 'department') {
        $other = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Other']);
        $student->update(['department_id' => $other->id]);
    } elseif ($mismatch === 'set') {
        $student->update(['set' => '2024/2025']);
    }
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->from('/dashboard/print/result')->post('/dashboard/print/result', [
        'exam_id' => $exam->id, 'reg_no' => $mismatch === 'missing' ? 'NOTFOUND' : $student->reg_no, 'pin' => $voucher->pin,
    ])->assertRedirect('/dashboard/print/result')->assertSessionHasErrors('reg_no');
    $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
})->with(['department', 'set', 'missing']);

test('vouchers from a different examination remain unused', function () {
    ['exam' => $exam, 'student' => $student, 'voucher' => $voucher] = printResultFixture();
    $other = Exam::create(['uuid' => (string) str()->uuid(), 'department_id' => $exam->department_id, 'title' => 'Other exam', 'set' => $exam->set, 'level' => $exam->level, 'start_date' => '2026-11-10', 'end_date' => '2026-11-20']);
    $voucher->update(['exam_id' => $other->id]);
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->post('/dashboard/print/result', ['exam_id' => $exam->id, 'reg_no' => $student->reg_no, 'pin' => $voucher->pin])->assertSessionHasErrors('pin');
    $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'exam_id' => $other->id]);
});

test('invalid result lookup input does not consume vouchers', function (array $input, string $field) {
    ['exam' => $exam, 'student' => $student, 'voucher' => $voucher] = printResultFixture();
    $staff = User::factory()->create(['status' => 'active']);
    $this->actingAs($staff)->from('/dashboard/print/result')->post('/dashboard/print/result', [
        ...['exam_id' => $exam->id, 'reg_no' => $student->reg_no, 'pin' => $voucher->pin], ...$input,
    ])->assertRedirect('/dashboard/print/result')->assertSessionHasErrors($field);
    $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
})->with([
    [['exam_id' => ''], 'exam_id'], [['exam_id' => 999], 'exam_id'], [['exam_id' => ['bad']], 'exam_id'],
    [['reg_no' => ''], 'reg_no'], [['reg_no' => ['bad']], 'reg_no'], [['reg_no' => str_repeat('a', 256)], 'reg_no'],
    [['pin' => ''], 'pin'], [['pin' => '123'], 'pin'], [['pin' => '12345678901a'], 'pin'], [['pin' => ['bad']], 'pin'],
]);
