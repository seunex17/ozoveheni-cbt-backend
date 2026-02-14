<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dashboard\CourseController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\ExamController;
    use App\Http\Controllers\Dashboard\ExamVoucherController;
    use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\StudentController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\AccountActiveMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [Authcontroller::class, 'index'])->name('home');
Route::get('/login', fn () => redirect()->route('home'));
Route::get('/reports/{uuid}', [ReportController::class, 'index'])->name('reports');

Route::post('/login', [Authcontroller::class, 'login'])->name('login');
Route::post('/logout', [Authcontroller::class, 'logout'])->name('logout');

Route::prefix('/dashboard')->middleware(['auth:web', AccountActiveMiddleware::class])->group(function () {
    Route::get('/', [Dashboardcontroller::class, 'index'])->name('dashboard');

    Route::post('/sweep-exam-hall', [Dashboardcontroller::class, 'sweepExamHall'])->name('sweepExamHall');

    // * Staffs
    Route::prefix('/staff')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('staff');
        Route::get('/{id}/update-password', [StaffController::class, 'updatePassword'])->name('staff.updatePassword');
        Route::get('/{id}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::get('/add', [StaffController::class, 'add'])->name('staff.add');

        Route::post('/{id}/update-password', [StaffController::class, 'updatePasswordPost'])->name('staff.updatePasswordPost');
        Route::post('/{id}/edit', [StaffController::class, 'editPost'])->name('staff.editPost');
        Route::post('/add', [StaffController::class, 'addPost'])->name('staff.addPost');
    });

    // Departments
    Route::prefix('/department')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('department');
        Route::get('/add', [DepartmentController::class, 'add'])->name('department.add');
        Route::get('/{uuid}/edit', [DepartmentController::class, 'edit'])->name('department.edit');

        Route::post('/add', [DepartmentController::class, 'addPost'])->name('department.addPost');
        Route::post('/{uuid}/edit', [DepartmentController::class, 'editPost'])->name('department.editPost');
        Route::post('/delete', [DepartmentController::class, 'delete'])->name('department.deletePost');
    });

    // Students
    Route::prefix('/student')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('student');
        Route::get('/department/{uuid}/{set}/{level}', [StudentController::class, 'department'])->name('student.department');
        Route::get('/department/{uuid}/{set}/{level}/add', [StudentController::class, 'add'])->name('student.add');
        Route::get('/{uuid}/edit', [StudentController::class, 'edit'])->name('student.edit');

        Route::post('/add', [StudentController::class, 'addPost'])->name('student.addPost');
        Route::post('/edit', [StudentController::class, 'editPost'])->name('student.editPost');
        Route::post('/delete', [StudentController::class, 'delete'])->name('student.deletePost');
    });

    // Course
    Route::prefix('/course')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('course');
        Route::get('/add', [CourseController::class, 'add'])->name('course.add');
        Route::get('/{uuid}/edit', [CourseController::class, 'edit'])->name('course.edit');

        Route::post('/add', [CourseController::class, 'addPost'])->name('course.addPost');
        Route::post('/{uuid}/edit', [CourseController::class, 'editPost'])->name('course.editPost');
        Route::post('/delete', [CourseController::class, 'delete'])->name('course.deletePost');
    });

    // Exams
    Route::prefix('/exam')->group(function () {
        Route::get('/', [ExamController::class, 'index'])->name('exam');
        Route::get('/add', [ExamController::class, 'add'])->name('exam.add');
        Route::get('/{uuid}/edit', [ExamController::class, 'edit'])->name('exam.edit');
        Route::get('{uuid}/view', [ExamController::class, 'view'])->name('exam.view');
        Route::get('/{uuid}/set-new-exam', [ExamController::class, 'setNewExam'])->name('exam.setNewExam');
        Route::get('/{uuid}/single-exam', [ExamController::class, 'singleExam'])->name('exam.singleExam');
        Route::get('/{uuid}/add-question', [ExamController::class, 'addQuestion'])->name('exam.addQuestion');
        Route::get('/{id}/view-question', [ExamController::class, 'viewQuestion'])->name('exam.viewQuestion');
        Route::get('/{uid}/reports', [ExamController::class, 'reports'])->name('exam.reports');
        Route::get('/{uuid}/broadsheet', [ExamController::class, 'broadsheet'])->name('exam.broadsheet');
        Route::get('/download-template', [ExamController::class, 'downloadTemplate'])->name('exam.downloadTemplate');

        Route::post('/add', [ExamController::class, 'addPost'])->name('exam.addPost');
        Route::post('/edit', [ExamController::class, 'editPost'])->name('exam.editPost');
        Route::post('/set-new-exam', [ExamController::class, 'setNewExamPost'])->name('exam.setNewExam');
        Route::post('/add-question', [ExamController::class, 'addQuestionPost'])->name('exam.addQuestionPost');
        Route::post('/delete-question', [ExamController::class, 'deleteQuestionPost'])->name('exam.deleteQuestionPost');
        Route::post('/delete-exam', [ExamController::class, 'deleteExamPost'])->name('exam.deleteExamPost');
        Route::post('/refresh-reports', [ExamController::class, 'refreshReports'])->name('exam.refreshReports');
        Route::post('/update=report', [ExamController::class, 'updateReport'])->name('exam.updateReports');
        Route::post('/import-questions', [ExamController::class, 'importQuestions'])->name('exam.importQuestions');
    });

    // Exam Vouchers
    Route::prefix('/voucher')->group(function () {
        Route::get('/', [ExamVoucherController::class, 'index'])->name('examVoucher');
        Route::get('/generate', [ExamVoucherController::class, 'generate'])->name('examVoucher.generate');
    });
});
