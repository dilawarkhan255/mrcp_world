<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\QuestionTemplateController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SelectExamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/exam', SelectExamController::class)->name('exam.select');
    Route::get('/questions/bank', [PracticeController::class, 'bank'])->name('questions.bank');
    Route::get('/questions/past-papers', [PracticeController::class, 'papers'])->name('questions.papers');
    Route::get('/questions/sessions', [PracticeController::class, 'index'])->name('practice.index');
    Route::post('/questions/sessions', [PracticeController::class, 'store'])->middleware('throttle:10,1')->name('practice.store');
    Route::get('/questions/sessions/{session}', [PracticeController::class, 'show'])->name('practice.show');
    Route::post('/questions/sessions/{session}/answer/{position}', [PracticeController::class, 'answer'])->middleware('throttle:120,1')->whereNumber('position')->name('practice.answer');
    Route::get('/questions/mocks', [MockExamController::class, 'index'])->name('mocks.index');
    Route::post('/questions/mocks', [MockExamController::class, 'store'])->middleware('throttle:10,1')->name('mocks.store');
    Route::get('/questions/mocks/{session}', [MockExamController::class, 'show'])->name('mocks.show');
    Route::post('/questions/mocks/{session}/answer/{position}', [MockExamController::class, 'answer'])->middleware('throttle:120,1')->whereNumber('position')->name('mocks.answer');
    Route::post('/questions/mocks/{session}/finish', [MockExamController::class, 'finish'])->name('mocks.finish');
    Route::get('/questions/review', [ReviewController::class, 'index'])->name('review.index');
    Route::post('/questions/review/practice', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('review.store');
    Route::get('/questions/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('/questions/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::post('/questions/comments', [CommentController::class, 'store'])->middleware('throttle:20,1')->name('comments.store');
    Route::post('/questions/comments/{comment}/reply', [CommentController::class, 'reply'])->middleware('throttle:20,1')->name('comments.reply');
    Route::post('/questions/comments/{comment}/moderate', [CommentController::class, 'moderate'])->middleware('throttle:30,1')->name('comments.moderate');
    Route::get('/admin/mcq-template', QuestionTemplateController::class)->name('imports.template');
    foreach (config('student.groups') as $group) {
        foreach ($group['items'] as $item) {
            if (! in_array($item['href'], ['/dashboard', '/questions/bank', '/questions/sessions', '/questions/past-papers', '/questions/mocks', '/questions/review', '/questions/performance', '/questions/comments'])) {
                Route::get($item['href'], fn () => Inertia::render('Placeholder', $item));
            }
        }
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
