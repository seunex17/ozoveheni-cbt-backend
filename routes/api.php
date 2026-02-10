<?php

use App\Http\Controllers\App\ExaminationController;
use Illuminate\Support\Facades\Route;

Route::get('/load-questions/{department}/{set}/{attempt}', [ExaminationController::class, 'loadQuestions'])->name('exam.loadQuestions');

Route::post('/student-login', [ExaminationController::class, 'studentLogin'])->name('exam.studentLogin');
Route::post('/save-answers', [ExaminationController::class, 'saveAnswers'])->name('exam.saveAnswers');
Route::post('/submit-exam', [ExaminationController::class, 'submitExam'])->name('exam.submitExam');

Route::get('/test', function () {
    broadcast(new \App\Events\ExamHallEvent(0))->toOthers();
});
