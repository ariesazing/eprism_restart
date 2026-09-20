<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\GuestSubmissionController;
use App\Http\Controllers\ManuscriptController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlyOfficeDocumentController;
use App\Http\Controllers\OnlyOfficeTemplateController;
use App\Http\Controllers\OnlyOfficeTransientDocumentController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RapmDocumentController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\ResearchSubmissionController;
use App\Http\Controllers\ReviewerSubmissionController;
use App\Http\Controllers\SimilarityCheckController;
use App\Http\Controllers\SimilarityNotificationController;
use App\Http\Controllers\SubmissionDiscussionController;
use App\Http\Controllers\SubmissionWindowController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

// The landing page for anyone not signed in — a choice between starting a new research
// draft (no account needed yet) and logging in. Dashboard and the repository both now
// require an account (see the 'auth' group below); this is deliberately the only
// unauthenticated view of the app besides the auth screens themselves.
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

Route::get('/get-started', [GuestSubmissionController::class, 'create'])->name('guest-submissions.create');

// Public so a prospective researcher can read the memorandum before ever creating an
// account — see SubmissionWindowController::memorandum().
Route::get('/submission-timeline/{researchType}/{classification}/memorandum', [SubmissionWindowController::class, 'memorandum'])->name('submission-timeline.memorandum');

// 'active' wraps the whole authenticated area (not just the role-specific groups below)
// so a disabled account is logged out on its very next request, dashboard/profile
// included — see EnsureAccountIsActive. There's no more "approved" gate to layer under
// it: a freshly registered account is active immediately.
// 'verified' requires a confirmed email before touching the app — self-registered
// accounts get a verification email (see RegisteredUserController + User::MustVerifyEmail);
// admin-created accounts are already pre-verified (see UserManagementController) so they
// skip straight through.
Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/manuscript-versions/{version}/docx', [ManuscriptController::class, 'version'])->name('manuscript-versions.docx');
    Route::get('/manuscript-versions/{version}/final', [ManuscriptController::class, 'finalPdf'])->name('manuscript-versions.final');
    Route::post('/manuscript-versions/{version}/retry-final', [ManuscriptController::class, 'retryFinalPdf'])->name('manuscript-versions.retry-final');
    Route::get('/repository', [RepositoryController::class, 'index'])->name('repository.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/rapm-documents/{document}', [RapmDocumentController::class, 'show'])->name('rapm-documents.show');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('role:researcher')->prefix('submissions')->name('submissions.')->group(function () {
        Route::get('/', [ResearchSubmissionController::class, 'index'])->name('index');
        Route::get('/create', [ResearchSubmissionController::class, 'create'])->name('create');
        Route::post('/', [ResearchSubmissionController::class, 'store'])->name('store');
        Route::get('/{submission}', [ResearchSubmissionController::class, 'show'])->name('show');
        Route::put('/{submission}', [ResearchSubmissionController::class, 'update'])->name('update');
        Route::get('/{submission}/manuscript-config', [ManuscriptController::class, 'config'])->name('manuscript.config');
        Route::get('/{submission}/manuscript-status', [ManuscriptController::class, 'status'])->name('manuscript.status');
        Route::post('/{submission}/manuscript-attachments', [ResearchSubmissionController::class, 'manuscriptAttachments'])->name('manuscript.attachments');
        Route::get('/{submission}/chapters', [ResearchSubmissionController::class, 'chapters'])->name('chapters');
        Route::patch('/{submission}/autosave', [ResearchSubmissionController::class, 'autosave'])->name('autosave');
        Route::post('/{submission}/submit', [ResearchSubmissionController::class, 'submit'])->name('submit');
        Route::post('/{submission}/resubmit', [ResearchSubmissionController::class, 'resubmit'])->name('resubmit');
        Route::get('/{submission}/attachments/{document}', [ResearchSubmissionController::class, 'download'])->name('attachments.download');
        Route::get('/{submission}/attachments/{document}/view', [ResearchSubmissionController::class, 'view'])->name('attachments.view');
        Route::delete('/{submission}/attachments/{document}', [ResearchSubmissionController::class, 'destroyAttachment'])->name('attachments.destroy');
        Route::get('/{submission}/proponents/{proponent}/photo', [ResearchSubmissionController::class, 'proponentPhoto'])->name('proponents.photo');
        Route::get('/{submission}/manuscript', [ResearchSubmissionController::class, 'manuscript'])->name('manuscript');
        Route::get('/{submission}/manuscript/versions/{snapshot}', [ResearchSubmissionController::class, 'manuscriptVersion'])->name('manuscript.version');
        Route::get('/{submission}/manuscript/review', [ResearchSubmissionController::class, 'reviewManuscript'])->name('manuscript.review');
        Route::get('/{submission}/manuscript/versions/{snapshot}/review', [ResearchSubmissionController::class, 'reviewManuscriptVersion'])->name('manuscript.version.review');
        Route::get('/{submission}/comments', [DocumentCommentController::class, 'index'])->name('comments.index');
        Route::get('/{submission}/sram', [ResearchSubmissionController::class, 'sram'])->name('sram');
        Route::post('/{submission}/grammar-check', [ResearchSubmissionController::class, 'grammarCheck'])->name('grammar-check');
        // throttle: every check fires ~25 searches through SearXNG at Google/Bing/DuckDuckGo from
        // this network's IP, and hammering them is what gets that IP CAPTCHA'd — see
        // SimilarityCheckController::store().
        Route::post('/{submission}/similarity', [SimilarityCheckController::class, 'store'])->middleware('throttle:5,10')->name('similarity.store');
        Route::get('/{submission}/similarity/{check}', [SimilarityCheckController::class, 'show'])->name('similarity.show');
        Route::get('/{submission}/similarity/{check}/status', [SimilarityCheckController::class, 'status'])->name('similarity.status');
        Route::get('/{submission}/sections/{section}/onlyoffice-config', [OnlyOfficeDocumentController::class, 'config'])->name('sections.onlyoffice-config');
        Route::post('/{submission}/sections/{section}/onlyoffice-force-save', [OnlyOfficeDocumentController::class, 'forceSave'])->name('sections.onlyoffice-force-save');
    });

    // The "your similarity check is done" popup, which every page a researcher can be on carries
    // (see App\View\Components\SimilarityNotifier) — not under /submissions, since it isn't about
    // any one submission.
    Route::middleware('role:researcher')->prefix('similarity')->name('similarity.')->group(function () {
        Route::get('/notifications', [SimilarityNotificationController::class, 'index'])->name('notifications');
        Route::post('/{check}/dismiss', [SimilarityNotificationController::class, 'dismiss'])->name('dismiss');
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
        Route::get('/{submission}/discussion', [SubmissionDiscussionController::class, 'index'])->name('discussion.index');
        Route::post('/{submission}/discussion', [SubmissionDiscussionController::class, 'store'])->name('discussion.store');
        Route::delete('/{submission}/discussion/{message}', [SubmissionDiscussionController::class, 'destroy'])->name('discussion.destroy');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users', [UserManagementController::class, 'batchUpdate'])->name('users.batch-update');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

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
        Route::get('/submissions/{submission}/discussion', [SubmissionDiscussionController::class, 'index'])->name('submissions.discussion.index');
        Route::post('/submissions/{submission}/discussion', [SubmissionDiscussionController::class, 'store'])->name('submissions.discussion.store');
        Route::delete('/submissions/{submission}/discussion/{message}', [SubmissionDiscussionController::class, 'destroy'])->name('submissions.discussion.destroy');
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
        Route::get('/document-templates/{templateKey}/onlyoffice-config', [OnlyOfficeTemplateController::class, 'config'])->name('document-templates.onlyoffice-config');
        Route::post('/document-templates/{templateKey}/manuscript-format', [DocumentTemplateController::class, 'updateManuscriptFormat'])->name('document-templates.manuscript-format.update');
        Route::post('/document-templates/{templateKey}/manuscript-format/preview', [DocumentTemplateController::class, 'previewManuscriptFormat'])->name('document-templates.manuscript-format.preview');

        Route::get('/organizational-units', [OrganizationalUnitController::class, 'index'])->name('organizational-units.index');
        Route::post('/organizational-units', [OrganizationalUnitController::class, 'store'])->name('organizational-units.store');
        Route::patch('/organizational-units', [OrganizationalUnitController::class, 'batchUpdate'])->name('organizational-units.batch-update');

        Route::get('/submission-timeline', [SubmissionWindowController::class, 'index'])->name('submission-timeline.index');
        Route::patch('/submission-timeline', [SubmissionWindowController::class, 'update'])->name('submission-timeline.update');
    });
});

// Called by ONLYOFFICE Document Server itself (server-to-server), never by a logged-in
// browser session — deliberately outside the ['auth','active','verified'] group above.
// Authorized purely by Laravel's signed-URL mechanism (ValidateSignature::relative(), since
// OnlyOfficeService signs the *relative* path so the host can be swapped for one Document
// Server can actually reach — see services.onlyoffice.callback_base_url); the callback route
// additionally verifies DS's own JWT inside the controller.
Route::middleware(ValidateSignature::relative())->prefix('onlyoffice')->name('onlyoffice.')->group(function () {
    Route::get('/manuscripts/{submission}/download', [ManuscriptController::class, 'download'])->name('manuscripts.download');
    Route::post('/manuscripts/{submission}/callback', [ManuscriptController::class, 'callback'])->name('manuscripts.callback');
    Route::get('/sections/{section}/download', [OnlyOfficeDocumentController::class, 'download'])->name('sections.download');
    Route::post('/sections/{section}/callback', [OnlyOfficeDocumentController::class, 'callback'])->name('sections.callback');
    Route::get('/templates/{template}/download', [OnlyOfficeTemplateController::class, 'download'])->name('templates.download');
    Route::post('/templates/{template}/callback', [OnlyOfficeTemplateController::class, 'callback'])->name('templates.callback');
    Route::get('/transient/{token}/download', [OnlyOfficeTransientDocumentController::class, 'download'])
        ->where('token', '[0-9a-fA-F-]{36}')
        ->name('transient.download');
});

require __DIR__.'/auth.php';
