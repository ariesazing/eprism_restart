<?php

use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\ResearchSubmissionController;
use App\Http\Controllers\ReviewerSubmissionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/repository', [RepositoryController::class, 'index'])->name('repository.index');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('approved')->group(function () {
        Route::middleware('role:researcher')->prefix('submissions')->name('submissions.')->group(function () {
            Route::get('/', [ResearchSubmissionController::class, 'index'])->name('index');
            Route::get('/create', [ResearchSubmissionController::class, 'create'])->name('create');
            Route::post('/', [ResearchSubmissionController::class, 'store'])->name('store');
            Route::get('/{submission}', [ResearchSubmissionController::class, 'show'])->name('show');
            Route::put('/{submission}', [ResearchSubmissionController::class, 'update'])->name('update');
            Route::post('/{submission}/submit', [ResearchSubmissionController::class, 'submit'])->name('submit');
            Route::post('/{submission}/revision', [ResearchSubmissionController::class, 'submitRevision'])->name('revision');
            Route::get('/{submission}/documents/{document}', [ResearchSubmissionController::class, 'download'])->name('documents.download');
        });

        Route::middleware('role:reviewer')->prefix('reviewer/submissions')->name('reviewer.submissions.')->group(function () {
            Route::get('/', [ReviewerSubmissionController::class, 'index'])->name('index');
            Route::get('/{submission}', [ReviewerSubmissionController::class, 'show'])->name('show');
            Route::post('/{submission}/review', [ReviewerSubmissionController::class, 'storeReview'])->name('review');
            Route::get('/{submission}/documents/{document}', [ReviewerSubmissionController::class, 'download'])->name('documents.download');
        });

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

            Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
            Route::patch('/submissions/{submission}/assign-reviewer', [AdminSubmissionController::class, 'assignReviewer'])->name('submissions.assign-reviewer');
            Route::patch('/reviews/{review}/approve', [AdminSubmissionController::class, 'approveReview'])->name('reviews.approve');
            Route::patch('/submissions/{submission}/request-revision', [AdminSubmissionController::class, 'requestRevision'])->name('submissions.request-revision');
            Route::patch('/submissions/{submission}/approve', [AdminSubmissionController::class, 'approveSubmission'])->name('submissions.approve');
            Route::get('/submissions/{submission}/documents/{document}', [AdminSubmissionController::class, 'download'])->name('submissions.documents.download');
            Route::get('/reports', [AdminSubmissionController::class, 'reports'])->name('reports');
        });
    });
});

require __DIR__.'/auth.php';
