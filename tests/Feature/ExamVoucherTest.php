<?php

use App\Models\Department;
use App\Models\Exam;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ExamVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function voucherExam(string $title = 'Semester examination'): Exam
{
    $department = Department::create(['uuid' => (string) str()->uuid(), 'name' => 'Community Health']);

    return Exam::create([
        'uuid' => (string) str()->uuid(), 'title' => $title, 'department_id' => $department->id,
        'set' => '2025/2026', 'level' => 'nd1', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20',
    ]);
}

test('voucher page and generation require active administrators', function () {
    $this->get('/dashboard/voucher')->assertRedirect('/login');
    $this->get('/dashboard/voucher/generate?qty=1&exam_id=1')->assertRedirect('/login');
    $staff = User::factory()->create(['is_admin' => false, 'status' => 'active']);
    $this->actingAs($staff)->get('/dashboard/voucher')->assertRedirect('/dashboard');
    $this->get('/dashboard/voucher/generate?qty=1&exam_id=1')->assertRedirect('/dashboard');
    $inactive = User::factory()->create(['is_admin' => true, 'status' => 'inactive']);
    $this->actingAs($inactive)->get('/dashboard/voucher')->assertRedirect('/');
    $this->assertDatabaseCount('vouchers', 0);
});

test('voucher generator supplies exam identity and department without voucher pins', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $exam = voucherExam();
    ExamVoucherService::generate(1, $exam);
    $this->actingAs($admin)->get('/dashboard/voucher')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard/ExamVoucher/Index')->has('exams', 1)
        ->where('exams.0.title', 'Semester examination')->where('exams.0.department.name', 'Community Health')
        ->where('exams.0.set', '2025/2026')->where('exams.0.level', 'nd1')->missing('vouchers'));
});

test('voucher page supports no examinations', function () {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->get('/dashboard/voucher')->assertInertia(fn (AssertableInertia $page) => $page->has('exams', 0));
});

test('invalid quantities are rejected before creating a voucher batch', function (mixed $quantity) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $exam = voucherExam();
    $this->actingAs($admin)->getJson('/dashboard/voucher/generate?'.http_build_query(['exam_id' => $exam->id, 'qty' => $quantity]))
        ->assertUnprocessable()->assertJsonValidationErrors('qty');
    $this->assertDatabaseCount('vouchers', 0);
})->with([null, '', 0, -1, 501, 1.5, 'invalid', [['unexpected']]]);

test('invalid exam selections are rejected before creating a voucher batch', function (mixed $examId) {
    $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
    $this->actingAs($admin)->getJson('/dashboard/voucher/generate?'.http_build_query(['exam_id' => $examId, 'qty' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('exam_id');
    $this->assertDatabaseCount('vouchers', 0);
})->with([null, 999, 'invalid', [['unexpected']]]);

test('voucher batches created at the same time remain separate for their selected exams', function () {
    $this->freezeTime();
    $firstExam = voucherExam('First exam');
    $secondExam = voucherExam('Second exam');
    $first = ExamVoucherService::generate(2, $firstExam);
    $second = ExamVoucherService::generate(3, $secondExam);
    expect($first)->not->toBe($second);
    expect(Voucher::where('reference', $first)->count())->toBe(2);
    expect(Voucher::where('reference', $second)->count())->toBe(3);
    expect(Voucher::where('reference', $first)->where('exam_id', $secondExam->id)->exists())->toBeFalse();
    expect(Voucher::where('reference', $second)->where('exam_id', $firstExam->id)->exists())->toBeFalse();
    $this->assertDatabaseCount('vouchers', 5);
});

test('voucher print sheet identifies its exam and only includes its selected batch', function () {
    $exam = voucherExam('Anatomy <exam>');
    $reference = ExamVoucherService::generate(2, $exam);
    $otherReference = ExamVoucherService::generate(1, $exam);
    $batch = Voucher::where('reference', $reference)->get();
    $other = Voucher::where('reference', $otherReference)->firstOrFail();
    $html = view('exam.voucher', ['exam' => $exam, 'vouchers' => $batch])->render();
    expect($html)->toContain('Anatomy &lt;exam&gt;', 'Set 2025/2026', 'ND1');
    foreach ($batch as $voucher) {
        expect($html)->toContain($voucher->serial, $voucher->pin);
    }
    expect($html)->not->toContain($other->serial);
});
