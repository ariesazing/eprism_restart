<?php

use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCommentController;
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
            Route::get('/{submission}/documents/{document}/view', [ResearchSubmissionController::class, 'view'])->name('documents.view');
            Route::get('/{submission}/documents/{document}/review', [ResearchSubmissionController::class, 'reviewDocument'])->name('documents.review');
            Route::get('/{submission}/documents/{document}/comments', [DocumentCommentController::class, 'index'])->name('documents.comments.index');
        });

        Route::middleware('role:reviewer')->prefix('reviewer/submissions')->name('reviewer.submissions.')->group(function () {
            Route::get('/', [ReviewerSubmissionController::class, 'index'])->name('index');
            Route::get('/{submission}', [ReviewerSubmissionController::class, 'show'])->name('show');
            Route::post('/{submission}/review', [ReviewerSubmissionController::class, 'storeReview'])->name('review');
            Route::get('/{submission}/documents/{document}', [ReviewerSubmissionController::class, 'download'])->name('documents.download');
            Route::get('/{submission}/documents/{document}/view', [ReviewerSubmissionController::class, 'view'])->name('documents.view');
            Route::get('/{submission}/documents/{document}/review', [ReviewerSubmissionController::class, 'reviewDocument'])->name('documents.review');
            Route::get('/{submission}/documents/{document}/comments', [DocumentCommentController::class, 'index'])->name('documents.comments.index');
            Route::post('/{submission}/documents/{document}/comments', [DocumentCommentController::class, 'store'])->name('documents.comments.store');
            Route::patch('/{submission}/documents/{document}/comments/{comment}', [DocumentCommentController::class, 'update'])->name('documents.comments.update');
            Route::delete('/{submission}/documents/{document}/comments/{comment}', [DocumentCommentController::class, 'destroy'])->name('documents.comments.destroy');
        });

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

            Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
            Route::patch('/submissions/{submission}/assign-reviewer', [AdminSubmissionController::class, 'assignReviewer'])->name('submissions.assign-reviewer');
            Route::patch('/reviews/{review}/approve', [AdminSubmissionController::class, 'approveReview'])->name('reviews.approve');
            Route::patch('/reviews/{review}', [AdminSubmissionController::class, 'updateReview'])->name('reviews.update');
            Route::patch('/reviews/{review}/reopen', [AdminSubmissionController::class, 'reopenReview'])->name('reviews.reopen');
            Route::patch('/submissions/{submission}/request-revision', [AdminSubmissionController::class, 'requestRevision'])->name('submissions.request-revision');
            Route::patch('/submissions/{submission}/approve', [AdminSubmissionController::class, 'approveSubmission'])->name('submissions.approve');
            Route::get('/submissions/{submission}/documents/{document}', [AdminSubmissionController::class, 'download'])->name('submissions.documents.download');
            Route::get('/submissions/{submission}/documents/{document}/view', [AdminSubmissionController::class, 'view'])->name('submissions.documents.view');
            Route::get('/submissions/{submission}/documents/{document}/review', [AdminSubmissionController::class, 'reviewDocument'])->name('submissions.documents.review');
            Route::get('/submissions/{submission}/documents/{document}/comments', [DocumentCommentController::class, 'index'])->name('submissions.documents.comments.index');
            Route::post('/submissions/{submission}/documents/{document}/comments', [DocumentCommentController::class, 'store'])->name('submissions.documents.comments.store');
            Route::patch('/submissions/{submission}/documents/{document}/comments/{comment}', [DocumentCommentController::class, 'update'])->name('submissions.documents.comments.update');
            Route::delete('/submissions/{submission}/documents/{document}/comments/{comment}', [DocumentCommentController::class, 'destroy'])->name('submissions.documents.comments.destroy');
            Route::get('/reports', [AdminSubmissionController::class, 'reports'])->name('reports');
        });
    });
});

require __DIR__.'/auth.php';
