<?php

use App\Http\Controllers\SelectExamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::post('/exam', SelectExamController::class)->name('exam.select');
    foreach (config('student.groups') as $group) {
        foreach ($group['items'] as $item) {
            if ($item['href'] !== '/dashboard') {
                Route::get($item['href'], fn () => Inertia::render('Placeholder', $item));
            }
        }
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
