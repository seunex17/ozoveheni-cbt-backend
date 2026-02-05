<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\StudentController;
use App\Http\Middleware\AccountActiveMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [Authcontroller::class, 'index'])->name('home');
Route::get('/login', fn () => redirect()->route('home'));

Route::post('/login', [Authcontroller::class, 'login'])->name('login');
Route::post('/logout', [Authcontroller::class, 'logout'])->name('logout');

Route::prefix('/dashboard')->middleware(['auth:web', AccountActiveMiddleware::class])->group(function () {
    Route::get('/', [Dashboardcontroller::class, 'index'])->name('dashboard');

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
        Route::get('/department/{uuid}/{set}', [StudentController::class, 'department'])->name('student.department');
        Route::get('/department/{uuid}/{set}/add', [StudentController::class, 'add'])->name('student.add');
        Route::get('/{uuid}/edit', [StudentController::class, 'edit'])->name('student.edit');

        Route::post('/add', [StudentController::class, 'addPost'])->name('student.addPost');
        Route::post('/edit', [StudentController::class, 'editPost'])->name('student.editPost');
        Route::post('/delete', [StudentController::class, 'delete'])->name('student.deletePost');
    });
});
