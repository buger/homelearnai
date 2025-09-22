<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\FlashcardExportController;
use App\Http\Controllers\FlashcardPreviewController;
use App\Http\Controllers\IcsImportController;
use App\Http\Controllers\KidsModeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // If user is authenticated, redirect to dashboard
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    // Otherwise, redirect to login page
    return redirect()->route('login');
});

// Dashboard routes
Route::middleware(['auth'])->group(function () {
    // Main dashboard redirects to parent dashboard
    Route::get('/dashboard', [DashboardController::class, 'parentDashboard'])->name('dashboard');

    // Parent dashboard
    Route::get('/dashboard/parent', [DashboardController::class, 'parentDashboard'])->name('dashboard.parent');

    // Child dashboard routes
    Route::get('/dashboard/child/{child}/today', [DashboardController::class, 'childToday'])->name('dashboard.child.today');
    Route::get('/dashboard/child-today/{child_id?}', [DashboardController::class, 'childToday'])->name('dashboard.child-today');

    // Dashboard actions
    Route::post('/dashboard/bulk-complete-today', [DashboardController::class, 'bulkCompleteToday'])->name('dashboard.bulk-complete-today');
    Route::put('/dashboard/child/{child}/independence-level', [DashboardController::class, 'updateIndependenceLevel'])->name('dashboard.independence-level');
});

// Authentication middleware for all protected routes
Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Onboarding routes
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::get('/onboarding/children', [OnboardingController::class, 'children'])->name('onboarding.children');
    Route::get('/onboarding/subjects', [OnboardingController::class, 'subjects'])->name('onboarding.subjects');
    Route::post('/onboarding/child', [OnboardingController::class, 'storeChild'])->name('onboarding.child.store');
    Route::post('/onboarding/children', [OnboardingController::class, 'storeChild'])->name('onboarding.children.store');
    Route::post('/onboarding/subjects', [OnboardingController::class, 'storeSubjects'])->name('onboarding.subjects.store');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');

    // Children management
    Route::resource('children', ChildController::class);

    // Subjects management
    Route::resource('subjects', SubjectController::class);
    Route::get('/subjects/quick-start/form', [SubjectController::class, 'quickStartForm'])->name('subjects.quick-start.form');
    Route::post('/subjects/quick-start', [SubjectController::class, 'quickStartStore'])->name('subjects.quick-start.store');
    Route::get('/subjects/{subject}/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/subjects/{subject}/units', [UnitController::class, 'store'])->name('subjects.units.store');

    // Units management (subject-scoped and direct)
    Route::get('/subjects/{subject}/units', [UnitController::class, 'index'])->name('subjects.units.index');
    Route::get('/subjects/{subject}/units/{unit}', [UnitController::class, 'show'])->name('subjects.units.show');
    Route::get('/subjects/{subject}/units/{unit}/edit', [UnitController::class, 'edit'])->name('subjects.units.edit');
    Route::put('/subjects/{subject}/units/{unit}', [UnitController::class, 'update'])->name('subjects.units.update');
    Route::delete('/subjects/{subject}/units/{unit}', [UnitController::class, 'destroy'])->name('subjects.units.destroy');

    // Direct unit routes for compatibility
    Route::get('/units/{unit}', [UnitController::class, 'showDirect'])->name('units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'editDirect'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'updateDirect'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroyDirect'])->name('units.destroy');

    // Topics management
    Route::resource('topics', TopicController::class);
    Route::get('/subjects/{subject}/units/{unit}/topics/create', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/units/{unit}/topics', [TopicController::class, 'storeForUnit'])->name('units.topics.store');
    Route::get('/units/{unit}/topics/{topic}', [TopicController::class, 'show'])->name('units.topics.show');

    // Topic materials management
    Route::post('/topics/{topic}/materials/video', [TopicController::class, 'addVideo'])->name('topics.materials.video');
    Route::post('/topics/{topic}/materials/link', [TopicController::class, 'addLink'])->name('topics.materials.link');
    Route::post('/topics/{topic}/materials/file', [TopicController::class, 'uploadFile'])->name('topics.materials.file');
    Route::delete('/topics/{topic}/materials/{type}/{index}', [TopicController::class, 'removeMaterial'])->name('topics.materials.remove');

    // Rich content management
    Route::post('/topics/{topic}/content/images', [TopicController::class, 'uploadContentImage'])->name('topics.content.images.upload');
    Route::get('/topics/{topic}/content/images', [TopicController::class, 'getContentImages'])->name('topics.content.images.list');
    Route::delete('/topics/{topic}/content/images/{index}', [TopicController::class, 'deleteContentImage'])->name('topics.content.images.delete');
    Route::post('/topics/content/preview', [TopicController::class, 'previewContent'])->name('topics.content.preview');

    // Unified markdown editor enhanced endpoints
    Route::post('/topics/content/preview-unified', [TopicController::class, 'previewUnifiedContent'])->name('topics.content.preview.unified');
    Route::post('/topics/content/export', [TopicController::class, 'exportContent'])->name('topics.content.export');
    Route::post('/topics/content/video-metadata', [TopicController::class, 'getVideoMetadata'])->name('topics.content.video.metadata');

    // Enhanced markdown editor file uploads
    Route::post('/topics/{topic}/markdown-upload', [TopicController::class, 'markdownFileUpload'])->name('topics.markdown.upload');

    // Chunked upload endpoints for Phase 5 enhanced file handling
    Route::post('/topics/{topic}/chunked-upload/start', [TopicController::class, 'startChunkedUpload'])->name('topics.chunked-upload.start');
    Route::post('/topics/{topic}/chunked-upload/chunk', [TopicController::class, 'uploadChunk'])->name('topics.chunked-upload.chunk');
    Route::post('/topics/{topic}/chunked-upload/finalize', [TopicController::class, 'finalizeChunkedUpload'])->name('topics.chunked-upload.finalize');

    // Kids view routes (protected by kids mode middleware)
    Route::middleware(['kids-mode'])->group(function () {
        Route::get('/units/{unit}/topics/{topic}/kids', [TopicController::class, 'showKidsView'])->name('topics.kids.show');
        Route::post('/topics/{topic}/kids/activity', [TopicController::class, 'trackKidsActivity'])->name('topics.kids.activity');
        Route::post('/topics/{topic}/kids/complete', [TopicController::class, 'completeForChild'])->name('topics.kids.complete');
    });

    // Planning board
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::get('/planning/sessions/create', [PlanningController::class, 'createSession'])->name('planning.create-session');
    Route::post('/planning/sessions', [PlanningController::class, 'createSession'])->name('planning.sessions.store');
    Route::patch('/planning/sessions/{sessionId}/status', [PlanningController::class, 'updateSessionStatus'])->name('planning.sessions.status');
    Route::patch('/planning/sessions/{sessionId}/schedule', [PlanningController::class, 'scheduleSession'])->name('planning.sessions.schedule');
    Route::patch('/planning/sessions/{sessionId}/unschedule', [PlanningController::class, 'unscheduleSession'])->name('planning.sessions.unschedule');
    Route::patch('/planning/sessions/{sessionId}/commitment-type', [PlanningController::class, 'updateSessionCommitmentType'])->name('planning.sessions.commitment-type');
    Route::get('/planning/sessions/{sessionId}/skip-modal', [PlanningController::class, 'showSkipDayModal'])->name('planning.sessions.skip-modal');
    Route::post('/planning/sessions/{sessionId}/skip', [PlanningController::class, 'skipSessionDay'])->name('planning.sessions.skip');
    Route::get('/planning/sessions/{sessionId}/reschedule-suggestions', [PlanningController::class, 'getSchedulingSuggestions'])->name('planning.sessions.reschedule-suggestions');
    Route::post('/planning/catch-up/redistribute', [PlanningController::class, 'redistributeCatchUp'])->name('planning.catch-up.redistribute');
    Route::patch('/planning/catch-up/{catchUpId}/priority', [PlanningController::class, 'updateCatchUpPriority'])->name('planning.catch-up.priority');

    // Calendar and ICS import
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/create', [CalendarController::class, 'create'])->name('calendar.create');
    Route::get('/calendar/import', [IcsImportController::class, 'index'])->name('calendar.import');
    Route::post('/calendar/import/file', [IcsImportController::class, 'importFile'])->name('calendar.import.file');
    Route::post('/calendar/import/url', [IcsImportController::class, 'importUrl'])->name('calendar.import.url');
    Route::get('/calendar/import/help', [IcsImportController::class, 'help'])->name('calendar.import.help');

    // Reviews system
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{child}/session', [ReviewController::class, 'startSession'])->name('reviews.session');
    Route::get('/reviews/{reviewId}/show', [ReviewController::class, 'show'])->name('reviews.show');
    Route::post('/reviews/{reviewId}/result', [ReviewController::class, 'processResult'])->name('reviews.process');
    Route::post('/reviews/{reviewId}/flashcard-result', [ReviewController::class, 'processFlashcardResult'])->name('reviews.flashcard.process');
    Route::post('/reviews/session/{sessionId}/complete', [ReviewController::class, 'completeSession'])->name('reviews.session.complete');

    // Review slots management
    Route::get('/reviews/child/{childId}/slots', [ReviewController::class, 'manageSlots'])->name('reviews.slots');
    Route::post('/reviews/slots', [ReviewController::class, 'storeSlot'])->name('reviews.slots.store');
    Route::put('/reviews/slots/{slotId}', [ReviewController::class, 'updateSlot'])->name('reviews.slots.update');
    Route::delete('/reviews/slots/{slotId}', [ReviewController::class, 'destroySlot'])->name('reviews.slots.destroy');
    Route::patch('/reviews/slots/{slotId}/toggle', [ReviewController::class, 'toggleSlot'])->name('reviews.slots.toggle');

    // Flashcard preview (for parents only - no database impact)
    Route::get('/preview/session/{sessionId}/next', [FlashcardPreviewController::class, 'getNextCard'])->name('flashcards.preview.next');
    Route::post('/preview/session/{sessionId}/answer', [FlashcardPreviewController::class, 'submitAnswer'])->name('flashcards.preview.answer');
    Route::get('/preview/session/{sessionId}/end', [FlashcardPreviewController::class, 'endPreview'])->name('flashcards.preview.end');
    Route::get('/preview/session/{sessionId}/status', [FlashcardPreviewController::class, 'getSessionStatus'])->name('flashcards.preview.status');

    // Topic-scoped flashcard routes (web interface)
    Route::get('/topics/{topicId}/flashcards/list', [FlashcardController::class, 'listView'])->name('topics.flashcards.list');
    Route::get('/topics/{topicId}/flashcards/create', [FlashcardController::class, 'create'])->name('topics.flashcards.create');
    Route::post('/topics/{topicId}/flashcards', [FlashcardController::class, 'storeView'])->name('topics.flashcards.store');
    Route::get('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'show'])->name('topics.flashcards.show');
    Route::get('/topics/{topicId}/flashcards/{flashcardId}/edit', [FlashcardController::class, 'edit'])->name('topics.flashcards.edit');
    Route::put('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'updateView'])->name('topics.flashcards.update');
    Route::delete('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'destroyView'])->name('topics.flashcards.destroy');

    // Topic flashcard preview
    Route::get('/topics/{topic}/flashcards/preview/start', [FlashcardPreviewController::class, 'startPreview'])->name('topics.flashcards.preview.start');

    // Unit flashcard preview (for backward compatibility)
    Route::get('/units/{unit}/flashcards/preview/start', [FlashcardPreviewController::class, 'startPreview'])->name('units.flashcards.preview.start');

    // Unit-based flashcard routes (backward compatibility)
    Route::get('/units/{unitId}/flashcards/export/options', [FlashcardExportController::class, 'options'])->name('flashcards.export.options');
    Route::post('/units/{unitId}/flashcards/export/preview', [FlashcardExportController::class, 'preview'])->name('flashcards.export.preview');
    Route::post('/units/{unitId}/flashcards/export/download', [FlashcardExportController::class, 'download'])->name('flashcards.export.download');
    Route::get('/units/{unitId}/flashcards/export/selection', [FlashcardExportController::class, 'bulkExportSelection'])->name('flashcards.export.selection');
    Route::get('/units/{unitId}/flashcards/export/stats', [FlashcardExportController::class, 'exportStats'])->name('flashcards.export.stats');
    Route::get('/units/{unitId}/flashcards/export/bulk-selection', [FlashcardExportController::class, 'bulkExportSelection'])->name('flashcards.export.bulk_selection');

    // Additional routes expected by templates
    Route::get('/units/{unit}/flashcards/export/bulk', [FlashcardExportController::class, 'bulkExportSelection'])->name('units.flashcards.export.bulk');
    Route::post('/units/{unit}/flashcards/export/preview', [FlashcardExportController::class, 'preview'])->name('units.flashcards.export.preview');

    // Flashcard print routes
    Route::get('/units/{unitId}/flashcards/print/options', [FlashcardController::class, 'showPrintOptions'])->name('flashcards.print.options');
    Route::post('/units/{unitId}/flashcards/print/preview', [FlashcardController::class, 'printPreview'])->name('flashcards.print.preview');
    Route::post('/units/{unitId}/flashcards/print/download', [FlashcardController::class, 'downloadPDF'])->name('flashcards.print.download');
    Route::get('/units/{unitId}/flashcards/print/bulk-selection', [FlashcardController::class, 'bulkPrintSelection'])->name('flashcards.print.bulk_selection');
    Route::get('/units/{unitId}/flashcards/print/bulk', [FlashcardController::class, 'bulkPrintSelection'])->name('units.flashcards.print.bulk');

    // Flashcard import routes (stubs for test compatibility)
    Route::get('/units/{unitId}/flashcards/import', [FlashcardController::class, 'showImportModal'])->name('flashcards.import');
    Route::post('/units/{unitId}/flashcards/import/execute', [FlashcardController::class, 'executeImport'])->name('flashcards.import.execute');
    Route::post('/units/{unitId}/flashcards/import/preview', [FlashcardController::class, 'previewImport'])->name('units.flashcards.import.preview');

    // Unit-scoped flashcard management routes (for unit-level flashcard operations)
    Route::get('/units/{unitId}/flashcards/{flashcardId}/edit', [FlashcardController::class, 'edit'])->name('units.flashcards.edit');
    Route::delete('/units/{unitId}/flashcards/{flashcardId}', [FlashcardController::class, 'destroy'])->name('units.flashcards.destroy');

    // API Routes for JSON responses (used by tests and API consumers)
    Route::prefix('api')->group(function () {
        // Topic-scoped flashcard routes
        Route::get('/topics/{topicId}/flashcards', [FlashcardController::class, 'index'])->name('api.topics.flashcards.index');
        Route::post('/topics/{topicId}/flashcards', [FlashcardController::class, 'store'])->name('api.topics.flashcards.store');
        Route::get('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'show'])->name('api.topics.flashcards.show');
        Route::put('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'update'])->name('api.topics.flashcards.update');
        Route::delete('/topics/{topicId}/flashcards/{flashcardId}', [FlashcardController::class, 'destroy'])->name('api.topics.flashcards.destroy');
        Route::delete('/topics/{topicId}/flashcards/{flashcardId}/force', [FlashcardController::class, 'forceDestroy'])->name('api.topics.flashcards.force-destroy');
        Route::post('/topics/{topicId}/flashcards/{flashcardId}/restore', [FlashcardController::class, 'restore'])->name('api.topics.flashcards.restore');
        Route::get('/topics/{topicId}/flashcards/type/{cardType}', [FlashcardController::class, 'getByType'])->name('api.topics.flashcards.by-type');
        Route::get('/topics/{topicId}/flashcards/stats', [FlashcardController::class, 'topicStats'])->name('api.topics.flashcards.stats');
        Route::post('/topics/{topicId}/flashcards/bulk', [FlashcardController::class, 'bulkTopicOperations'])->name('api.topics.flashcards.bulk');
        Route::patch('/topics/{topicId}/flashcards/bulk-status', [FlashcardController::class, 'bulkUpdateTopicStatus'])->name('api.topics.flashcards.bulk-status');

        // Unit-scoped flashcard routes (returns flashcards from all topics in unit)
        Route::get('/units/{unitId}/flashcards', [FlashcardController::class, 'index'])->name('api.units.flashcards.index');
        Route::post('/units/{unitId}/flashcards', [FlashcardController::class, 'store'])->name('api.units.flashcards.store');
        Route::put('/units/{unitId}/flashcards/{flashcardId}', [FlashcardController::class, 'update'])->name('api.units.flashcards.update');
        Route::delete('/units/{unitId}/flashcards/{flashcardId}', [FlashcardController::class, 'destroy'])->name('api.units.flashcards.destroy');
        Route::patch('/units/{unitId}/flashcards/bulk-status', [FlashcardController::class, 'bulkUpdateStatus'])->name('api.units.flashcards.bulk-status');

        // Flashcard management across topics
        Route::post('/flashcards/{flashcardId}/move', [FlashcardController::class, 'moveToTopic'])->name('api.flashcards.move');

        // Search endpoints (stubs for test compatibility)
        Route::get('/units/{unitId}/flashcards/search', [FlashcardController::class, 'searchStub'])->name('api.units.flashcards.search');
    });

    // Tasks (legacy/fallback)
    Route::resource('tasks', TaskController::class);

    // Kids Mode
    Route::get('/kids-mode/setup', [KidsModeController::class, 'showPinSettings'])->name('kids-mode.setup');
    Route::get('/kids-mode/settings', [KidsModeController::class, 'showPinSettings'])->name('kids-mode.settings');
    Route::post('/kids-mode/setup', [KidsModeController::class, 'updatePin'])->name('kids-mode.pin.update');
    Route::post('/kids-mode/settings/pin', [KidsModeController::class, 'updatePin'])->name('kids-mode.settings.pin');
    Route::post('/kids-mode/reset-pin', [KidsModeController::class, 'resetPin'])->name('kids-mode.pin.reset');
    Route::post('/kids-mode/{child}/enter', [KidsModeController::class, 'enterKidsMode'])->name('kids-mode.enter');
    Route::post('/dashboard/kids-mode/{child}/enter', [KidsModeController::class, 'enterKidsMode'])->name('dashboard.kids-mode.enter');
    Route::get('/kids-mode/exit', [KidsModeController::class, 'showExitScreen'])->name('kids-mode.exit');
    Route::post('/kids-mode/exit', [KidsModeController::class, 'validateExitPin'])->name('kids-mode.exit.validate');

    // Locale switching
    Route::post('/locale', [LocaleController::class, 'updateLocale'])->name('locale.update');
    Route::get('/translations/{locale}', [LocaleController::class, 'getTranslations'])->name('locale.translations');

});

// Public routes (no authentication required)
// Translation files for JavaScript
Route::get('/lang/{locale}.json', function ($locale) {
    // Validate locale to prevent directory traversal
    if (! in_array($locale, ['en', 'ru'])) {
        abort(404);
    }

    $path = lang_path("{$locale}.json");

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('translations.json');

require __DIR__.'/auth.php';
