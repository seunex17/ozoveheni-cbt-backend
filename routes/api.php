<?php

use App\Http\Controllers\App\ExamHallProgressController;
use App\Http\Controllers\App\ExaminationController;
use Illuminate\Support\Facades\Route;

Route::get('/load-questions/{department}/{set}/{attempt}', [ExaminationController::class, 'loadQuestions'])->name('exam.loadQuestions');

Route::post('/student-login', [ExaminationController::class, 'studentLogin'])->name('exam.studentLogin');
Route::post('/save-answers', [ExaminationController::class, 'saveAnswers'])->name('exam.saveAnswers');
Route::post('/submit-exam', [ExaminationController::class, 'submitExam'])->name('exam.submitExam');
Route::post('/resume-exam', [ExaminationController::class, 'resumeExam'])->name('exam.resumeExam');
Route::get('/attempt-status/{attempt}', [ExaminationController::class, 'attemptStatus'])->whereNumber('attempt')->name('exam.attemptStatus');

Route::get('/test', function () {
    broadcast(new \App\Events\ExamHallEvent(0))->toOthers();
});

Route::post('/exam-hall/heartbeat', [ExamHallProgressController::class, 'store'])->name('exam.hallHeartbeat');
