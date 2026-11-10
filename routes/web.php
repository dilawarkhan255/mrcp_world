<?php

use App\Http\Controllers\AiTutorController;
use App\Http\Controllers\AtlasController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamDemoController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\QuestionTemplateController;
use App\Http\Controllers\QuickSearchController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SelectExamController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\StudentTableController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TextbookController;
use App\Support\LegalText;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/exams/{slug}/demo', [ExamDemoController::class, 'show'])->name('landing.demo');
// Texts in config/terms.php and config/privacy.php, owner details in config/legal.php.
Route::get('/terms', fn () => Inertia::render('Legal/Terms', ['appName' => (string) config('app.name'), ...LegalText::document('terms')]))->name('legal.terms');
Route::get('/privacy', fn () => Inertia::render('Legal/Privacy', ['appName' => (string) config('app.name'), ...LegalText::document('privacy')]))->name('legal.privacy');
Route::get('/contact', fn () => Inertia::render('Legal/Contact', [
    'appName' => (string) config('app.name'),
    'email' => (string) config('legal.email'),
    'address' => (string) config('legal.address'),
    'whatsapp' => config('services.community.whatsapp_url') ?: null,
]))->name('contact');
Route::get('/cookie-settings', fn () => Inertia::render('Legal/Cookies', ['appName' => (string) config('app.name'), ...LegalText::document('cookies')]))->name('cookie-settings');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
// Subscribing and paying (manual or by card on Stripe Checkout). Logged in, but
// no email check yet: a new student pays straight after creating the account.
Route::middleware('auth')->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription', [SubscriptionController::class, 'store'])->middleware('throttle:5,1')->name('subscription.store');
    Route::post('/subscription/checkout', [SubscriptionCheckoutController::class, 'store'])->middleware('throttle:10,1')->name('subscription.checkout');
    Route::get('/subscription/checkout/success', [SubscriptionCheckoutController::class, 'success'])->name('subscription.checkout.success');
});
// Opened in a browser: say what this address is instead of a "method not allowed" error.
Route::get('/stripe/webhook', fn () => response(
    '<!doctype html><meta charset="utf-8"><title>Stripe webhook</title>'
    .'<body style="font-family:sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.5">'
    .'<h1 style="font-size:1.25rem">Stripe webhook</h1>'
    .'<p>This address receives payment events from Stripe. Stripe sends them as POST requests, so opening it in a browser does nothing.</p>'
    .'<p>To test payments, buy a plan with a Stripe test card, or run <code>stripe listen --forward-to '.e(url('/stripe/webhook')).'</code>.</p>'
    .'<p><a href="'.e(url('/')).'">Back to the home page</a></p></body>',
))->name('stripe.webhook.info');
// Signed by Stripe (checked in the controller); exempt from CSRF in bootstrap/app.php.
Route::post('/stripe/webhook', StripeWebhookController::class)->middleware('throttle:120,1')->name('stripe.webhook');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/exam', SelectExamController::class)->name('exam.select');
    Route::get('/questions/bank', [PracticeController::class, 'bank'])->name('questions.bank');
    Route::get('/questions/past-papers', [PracticeController::class, 'papers'])->middleware('qbank')->name('questions.papers');
    Route::get('/questions/subjects/{slug}', [SubjectController::class, 'main'])->where('slug', '[a-z0-9-]+')->name('subjects.main');
    Route::get('/questions/subjects/{slug}/past-papers', [SubjectController::class, 'past'])->where('slug', '[a-z0-9-]+')->middleware('qbank')->name('subjects.past');
    Route::get('/questions/sessions', [PracticeController::class, 'index'])->name('practice.index');
    Route::post('/questions/sessions', [PracticeController::class, 'store'])->middleware('throttle:10,1')->name('practice.store');
    // Yajra data tables (registered before the {session} routes, so "data" is not read as a session id).
    Route::get('/questions/sessions/data', [StudentTableController::class, 'sessions'])->middleware('throttle:120,1')->name('tables.sessions');
    Route::get('/questions/mocks/data', [StudentTableController::class, 'mocks'])->middleware(['qbank', 'throttle:120,1'])->name('tables.mocks');
    Route::get('/questions/sessions/{session}', [PracticeController::class, 'show'])->name('practice.show');
    Route::post('/questions/sessions/{session}/answer/{position}', [PracticeController::class, 'answer'])->middleware('throttle:120,1')->whereNumber('position')->name('practice.answer');
    Route::post('/questions/sessions/{session}/read/{position}', [PracticeController::class, 'read'])->middleware('throttle:120,1')->whereNumber('position')->name('practice.read');
    Route::get('/questions/mocks', [MockExamController::class, 'index'])->middleware('qbank')->name('mocks.index');
    Route::post('/questions/mocks', [MockExamController::class, 'store'])->middleware('throttle:10,1')->middleware('qbank')->name('mocks.store');
    Route::get('/questions/mocks/{session}', [MockExamController::class, 'show'])->middleware('qbank')->name('mocks.show');
    Route::post('/questions/mocks/{session}/answer/{position}', [MockExamController::class, 'answer'])->middleware('throttle:120,1')->whereNumber('position')->middleware('qbank')->name('mocks.answer');
    Route::post('/questions/mocks/{session}/finish', [MockExamController::class, 'finish'])->middleware('qbank')->name('mocks.finish');
    Route::get('/questions/review', [ReviewController::class, 'index'])->middleware('qbank')->name('review.index');
    Route::post('/questions/review/practice', [ReviewController::class, 'store'])->middleware('throttle:10,1')->middleware('qbank')->name('review.store');
    Route::get('/questions/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->middleware(['qbank', 'throttle:60,1'])->name('leaderboard.index');
    Route::get('/study-plan', [StudyPlanController::class, 'index'])->middleware('qbank')->name('study-plan.index');
    Route::post('/study-plan/ai', [AiTutorController::class, 'studyPlan'])->middleware(['qbank', 'throttle:6,1'])->name('ai.study-plan');
    Route::post('/questions/sessions/{session}/ask/{position}', [AiTutorController::class, 'ask'])->middleware(['qbank', 'throttle:10,1'])->whereNumber('position')->name('ai.ask');
    Route::get('/questions/comments', [CommentController::class, 'index'])->middleware('qbank')->name('comments.index');
    Route::post('/questions/comments', [CommentController::class, 'store'])->middleware('throttle:20,1')->middleware('qbank')->name('comments.store');
    Route::post('/questions/comments/{comment}/reply', [CommentController::class, 'reply'])->middleware('throttle:20,1')->middleware('qbank')->name('comments.reply');
    Route::post('/questions/comments/{comment}/moderate', [CommentController::class, 'moderate'])->middleware('throttle:30,1')->name('comments.moderate');
    Route::get('/questions/search', [SearchController::class, 'index'])->middleware('throttle:60,1')->middleware('qbank')->name('search.index');
    Route::post('/questions/search/practice', [SearchController::class, 'practice'])->middleware('throttle:10,1')->middleware('qbank')->name('search.practice');
    Route::get('/revision/notes', [BookmarkController::class, 'notes'])->middleware('qbank')->name('bookmarks.notes');
    Route::get('/revision/bookmarks', [BookmarkController::class, 'index'])->middleware('qbank')->name('bookmarks.index');
    Route::post('/revision/bookmarks', [BookmarkController::class, 'store'])->middleware('throttle:30,1')->name('bookmarks.store');
    Route::patch('/revision/bookmarks/{bookmark}', [BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::delete('/revision/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::post('/revision/bookmarks/practice', [BookmarkController::class, 'practice'])->middleware('throttle:10,1')->middleware('qbank')->name('bookmarks.practice');
    Route::get('/image-atlas', [AtlasController::class, 'index'])->middleware('throttle:60,1')->middleware('qbank')->name('atlas.index');
    Route::get('/search/quick', QuickSearchController::class)->middleware('throttle:90,1')->name('search.quick');
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('throttle:60,1')->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');
    Route::get('/textbooks', [TextbookController::class, 'index'])->middleware('qbank')->name('textbooks.index');
    Route::get('/textbooks/{slug}', [TextbookController::class, 'show'])->where('slug', '[a-z0-9-]+')->middleware('qbank')->name('textbooks.show');
    // Flashcards with spaced repetition, and the Knowledge Tutor (AI conversation).
    Route::get('/flashcards', [FlashcardController::class, 'index'])->middleware('qbank')->name('flashcards.index');
    Route::get('/flashcards/study', [FlashcardController::class, 'study'])->middleware('qbank')->name('flashcards.study');
    Route::post('/flashcards/{flashcard}/review', [FlashcardController::class, 'review'])->middleware(['qbank', 'throttle:120,1'])->name('flashcards.review');
    Route::get('/tutor', [AiTutorController::class, 'tutor'])->middleware('qbank')->name('tutor.index');
    Route::post('/tutor/chat', [AiTutorController::class, 'tutorChat'])->middleware(['qbank', 'throttle:10,1'])->name('tutor.chat');
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:5,1')->name('feedback.store');
    Route::get('/admin/mcq-template', QuestionTemplateController::class)->name('imports.template');
    foreach (config('student.placeholders') as $href => $title) {
        Route::get($href, fn () => Inertia::render('Placeholder', ['title' => $title, 'href' => $href]));
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
