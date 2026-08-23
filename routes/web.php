<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RapmDocumentController;
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

    Route::get('/rapm-documents/{document}', [RapmDocumentController::class, 'show'])->name('rapm-documents.show');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('approved')->group(function () {
        Route::middleware('role:researcher')->prefix('submissions')->name('submissions.')->group(function () {
            Route::get('/', [ResearchSubmissionController::class, 'index'])->name('index');
            Route::get('/create', [ResearchSubmissionController::class, 'create'])->name('create');
            Route::post('/', [ResearchSubmissionController::class, 'store'])->name('store');
            Route::get('/{submission}', [ResearchSubmissionController::class, 'show'])->name('show');
            Route::put('/{submission}', [ResearchSubmissionController::class, 'update'])->name('update');
            Route::patch('/{submission}/autosave', [ResearchSubmissionController::class, 'autosave'])->name('autosave');
            Route::post('/{submission}/submit', [ResearchSubmissionController::class, 'submit'])->name('submit');
            Route::post('/{submission}/resubmit', [ResearchSubmissionController::class, 'resubmit'])->name('resubmit');
            Route::get('/{submission}/attachments/{document}', [ResearchSubmissionController::class, 'download'])->name('attachments.download');
            Route::get('/{submission}/attachments/{document}/view', [ResearchSubmissionController::class, 'view'])->name('attachments.view');
            Route::get('/{submission}/manuscript', [ResearchSubmissionController::class, 'manuscript'])->name('manuscript');
            Route::get('/{submission}/manuscript/review', [ResearchSubmissionController::class, 'reviewManuscript'])->name('manuscript.review');
            Route::get('/{submission}/comments', [DocumentCommentController::class, 'index'])->name('comments.index');
            Route::get('/{submission}/sram', [ResearchSubmissionController::class, 'sram'])->name('sram');
        });

        Route::middleware('role:reviewer')->prefix('reviewer/submissions')->name('reviewer.submissions.')->group(function () {
            Route::get('/', [ReviewerSubmissionController::class, 'index'])->name('index');
            Route::get('/{submission}', [ReviewerSubmissionController::class, 'show'])->name('show');
            Route::post('/{submission}/review', [ReviewerSubmissionController::class, 'storeReview'])->name('review');
            Route::get('/{submission}/attachments/{document}', [ReviewerSubmissionController::class, 'download'])->name('attachments.download');
            Route::get('/{submission}/attachments/{document}/view', [ReviewerSubmissionController::class, 'view'])->name('attachments.view');
            Route::get('/{submission}/manuscript', [ReviewerSubmissionController::class, 'manuscript'])->name('manuscript');
            Route::get('/{submission}/manuscript/versions/{snapshot}', [ReviewerSubmissionController::class, 'manuscriptVersion'])->name('manuscript.version');
            Route::get('/{submission}/manuscript/review', [ReviewerSubmissionController::class, 'reviewManuscript'])->name('manuscript.review');
            Route::get('/{submission}/manuscript/versions/{snapshot}/review', [ReviewerSubmissionController::class, 'reviewManuscriptVersion'])->name('manuscript.version.review');
            Route::get('/{submission}/comments', [DocumentCommentController::class, 'index'])->name('comments.index');
            Route::post('/{submission}/comments', [DocumentCommentController::class, 'store'])->name('comments.store');
            Route::patch('/{submission}/comments/{comment}', [DocumentCommentController::class, 'update'])->name('comments.update');
            Route::delete('/{submission}/comments/{comment}', [DocumentCommentController::class, 'destroy'])->name('comments.destroy');
        });

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

            Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
            Route::patch('/submissions/{submission}/assign-reviewer', [AdminSubmissionController::class, 'assignReviewer'])->name('submissions.assign-reviewer');
            Route::get('/submissions/{submission}/attachments/{document}', [AdminSubmissionController::class, 'download'])->name('submissions.attachments.download');
            Route::get('/submissions/{submission}/attachments/{document}/view', [AdminSubmissionController::class, 'view'])->name('submissions.attachments.view');
            Route::get('/submissions/{submission}/manuscript', [AdminSubmissionController::class, 'manuscript'])->name('submissions.manuscript');
            Route::get('/submissions/{submission}/manuscript/versions/{snapshot}', [AdminSubmissionController::class, 'manuscriptVersion'])->name('submissions.manuscript.version');
            Route::get('/submissions/{submission}/manuscript/review', [AdminSubmissionController::class, 'reviewManuscript'])->name('submissions.manuscript.review');
            Route::get('/submissions/{submission}/manuscript/versions/{snapshot}/review', [AdminSubmissionController::class, 'reviewManuscriptVersion'])->name('submissions.manuscript.version.review');
            Route::get('/submissions/{submission}/comments', [DocumentCommentController::class, 'index'])->name('submissions.comments.index');
            Route::post('/submissions/{submission}/comments', [DocumentCommentController::class, 'store'])->name('submissions.comments.store');
            Route::patch('/submissions/{submission}/comments/{comment}', [DocumentCommentController::class, 'update'])->name('submissions.comments.update');
            Route::delete('/submissions/{submission}/comments/{comment}', [DocumentCommentController::class, 'destroy'])->name('submissions.comments.destroy');
            Route::get('/reports', [AdminSubmissionController::class, 'reports'])->name('reports');
            Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

            Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            // Registered before the {templateKey} routes below — those match any single
            // path segment (including the literal "images"), so this must win first.
            Route::post('/document-templates/images', [DocumentTemplateController::class, 'uploadImage'])->name('document-templates.images.store');
            Route::get('/document-templates/images/{filename}', [DocumentTemplateController::class, 'showImage'])->name('document-templates.images.show');
            Route::get('/document-templates/{templateKey}/edit', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
            Route::post('/document-templates/{templateKey}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
            Route::post('/document-templates/{templateKey}/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
        });
    });
});

require __DIR__.'/auth.php';
