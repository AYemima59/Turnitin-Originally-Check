<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Admin\SourceController;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::post('/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::post('/submissions/{submission}/recheck', [SubmissionController::class, 'recheck'])->name('submissions.recheck');
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');
    Route::get('/reports/{submission}', [ReportController::class, 'show'])->name('reports.show');

    Route::middleware('can:manage-sources')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
        Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');
        Route::patch('/sources/{source}/toggle', [SourceController::class, 'toggle'])->name('sources.toggle');
        Route::delete('/sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
    });
});

require __DIR__.'/auth.php';