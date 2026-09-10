<?php

use Alpha\Journal\Http\Controllers\Admin\AiController as AdminAiController;
use Alpha\Journal\Http\Controllers\Admin\JournalController as AdminJournalController;
use Alpha\Journal\Http\Controllers\Admin\NotificationSettingsController as AdminNotificationSettingsController;
use Alpha\Journal\Http\Controllers\Admin\StorageController as AdminStorageController;
use Alpha\Journal\Http\Controllers\BoardController;
use Alpha\Journal\Http\Controllers\CmsController;
use Alpha\Journal\Http\Controllers\DashboardController;
use Alpha\Journal\Http\Controllers\HeroSlideController;
use Alpha\Journal\Http\Controllers\LocaleController;
use Alpha\Journal\Http\Controllers\OaiController;
use Alpha\Journal\Http\Controllers\SearchController;
use Alpha\Journal\Http\Controllers\SiteController;
use Alpha\Journal\Http\Controllers\SitePageController;
use Alpha\Journal\Http\Controllers\TelegramWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Public site. `site.pages.show` is the reader-facing content page — deliberately outside the
// cms.pages.* admin namespace below, which is what keeps the two from colliding.
Route::get('/', [SiteController::class, 'home'])->name('home');
// Reader-facing article search. Public and unauthenticated — this is how research is found.
Route::get('search', SearchController::class)->name('search');

Route::get('journals', [SiteController::class, 'journals'])->name('site.journals');
Route::get('pages/{slug}', [SitePageController::class, 'show'])->name('site.pages.show');

// Language switch. POST because it writes session state that outlives the request.
Route::post('locale', LocaleController::class)->name('locale.switch');

// Landing-page content management. Every action is authorized per-method via
// SitePolicy::manage rather than by middleware, matching the rest of the app.
Route::middleware(['auth'])->prefix('cms')->name('cms.')->group(function () {
    Route::get('/', [CmsController::class, 'index'])->name('index');

    Route::get('homepage', [CmsController::class, 'homepage'])->name('homepage.edit');
    Route::put('homepage', [CmsController::class, 'updateHomepage'])->name('homepage.update');

    Route::get('appearance', [CmsController::class, 'appearance'])->name('appearance.edit');
    Route::put('appearance', [CmsController::class, 'updateAppearance'])->name('appearance.update');

    Route::get('menu', [CmsController::class, 'menu'])->name('menu.edit');
    Route::put('menu', [CmsController::class, 'updateMenu'])->name('menu.update');

    Route::get('footer', [CmsController::class, 'footer'])->name('footer.edit');
    Route::put('footer', [CmsController::class, 'updateFooter'])->name('footer.update');

    // The "Indexed by" and "Tools" strips on the public site.
    Route::get('badges', [CmsController::class, 'badges'])->name('badges.index');
    Route::post('badges', [CmsController::class, 'storeBadge'])->name('badges.store');
    Route::post('badges/{badge}', [CmsController::class, 'updateBadge'])->name('badges.update');
    Route::delete('badges/{badge}', [CmsController::class, 'destroyBadge'])->name('badges.destroy');

    Route::get('hero-slides', [CmsController::class, 'heroSlides'])->name('hero-slides.index');
    Route::post('hero-slides', [HeroSlideController::class, 'store'])->name('hero-slides.store');
    Route::delete('hero-slides/{heroSlide}', [HeroSlideController::class, 'destroy'])->name('hero-slides.destroy');

    // Editorial board: groups of people, each individually editable, reorderable and deletable.
    Route::get('editorial-board', [BoardController::class, 'index'])->name('board.index');
    Route::post('editorial-board/groups', [BoardController::class, 'storeGroup'])->name('board.groups.store');
    Route::put('editorial-board/groups/{boardGroup}', [BoardController::class, 'updateGroup'])->name('board.groups.update');
    Route::delete('editorial-board/groups/{boardGroup}', [BoardController::class, 'destroyGroup'])->name('board.groups.destroy');
    Route::post('editorial-board/groups/{boardGroup}/move', [BoardController::class, 'moveGroup'])->name('board.groups.move');
    Route::post('editorial-board/groups/{boardGroup}/members', [BoardController::class, 'storeMember'])->name('board.members.store');
    Route::post('editorial-board/members/{boardMember}', [BoardController::class, 'updateMember'])->name('board.members.update');
    Route::delete('editorial-board/members/{boardMember}', [BoardController::class, 'destroyMember'])->name('board.members.destroy');
    Route::post('editorial-board/members/{boardMember}/move', [BoardController::class, 'moveMember'])->name('board.members.move');

    Route::get('pages', [SitePageController::class, 'index'])->name('pages.index');
    Route::get('pages/create', [SitePageController::class, 'create'])->name('pages.create');
    Route::post('pages', [SitePageController::class, 'store'])->name('pages.store');
    Route::get('pages/{page}/edit', [SitePageController::class, 'edit'])->name('pages.edit');
    Route::put('pages/{page}', [SitePageController::class, 'update'])->name('pages.update');
    Route::delete('pages/{page}', [SitePageController::class, 'destroy'])->name('pages.destroy');
});

// The CMS used to live here — keep old links and bookmarks working.
Route::middleware(['auth'])->get('site/settings', fn () => redirect()->route('cms.homepage.edit'));

// Site administration. {journal} binds by id here, not by path like the public routes do — an
// admin has to be able to open a disabled journal, and this group deliberately omits the
// `journal` middleware, which is the wrong resolver for it.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('journals', [AdminJournalController::class, 'index'])->name('journals.index');
    Route::get('journals/create', [AdminJournalController::class, 'create'])->name('journals.create');
    Route::post('journals', [AdminJournalController::class, 'store'])->name('journals.store');
    Route::get('journals/{journal}/edit', [AdminJournalController::class, 'edit'])->name('journals.edit');
    Route::put('journals/{journal}', [AdminJournalController::class, 'update'])->name('journals.update');

    // AI: provider credentials, the installation switch, and the ai_runs audit trail.
    Route::get('ai', [AdminAiController::class, 'index'])->name('ai.index');
    Route::put('ai/key', [AdminAiController::class, 'updateKey'])->name('ai.key.update');
    Route::post('ai/test', [AdminAiController::class, 'test'])->name('ai.test');
    Route::put('ai/switch', [AdminAiController::class, 'updateSwitch'])->name('ai.switch');

    // Where uploaded files live, and moving them from the local disk to a bucket.
    Route::get('storage', [AdminStorageController::class, 'index'])->name('storage.index');
    Route::put('storage', [AdminStorageController::class, 'update'])->name('storage.update');
    Route::delete('storage/secret', [AdminStorageController::class, 'forget'])->name('storage.forget');
    Route::post('storage/test', [AdminStorageController::class, 'test'])->name('storage.test');
    Route::post('storage/migrate', [AdminStorageController::class, 'migrate'])->name('storage.migrate');

    // Delivery settings: Brevo SMTP credential and the Telegram bot.
    Route::get('notifications', [AdminNotificationSettingsController::class, 'index'])->name('notifications.index');
    Route::put('notifications/secret', [AdminNotificationSettingsController::class, 'updateSecret'])->name('notifications.secret');
    Route::post('notifications/test-email', [AdminNotificationSettingsController::class, 'testEmail'])->name('notifications.test-email');
    Route::post('notifications/telegram/webhook', [AdminNotificationSettingsController::class, 'registerWebhook'])->name('notifications.telegram.register');
    Route::post('notifications/telegram/test', [AdminNotificationSettingsController::class, 'testTelegram'])->name('notifications.telegram.test');
});

/*
 * Telegram bot updates. Deliberately outside every auth group — Telegram is not a logged-in user
 * — and guarded instead by the shared secret it echoes in X-Telegram-Bot-Api-Secret-Token, which
 * TelegramWebhookController verifies before touching the payload.
 *
 * Also outside the CSRF-protected web group: Telegram cannot present a session token. It is
 * registered here rather than in routes/api.php only to keep every HTTP entry point in one file.
 */
Route::post('telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('telegram.webhook');

// OAI-PMH 2.0 provider (CLAUDE.md §5.1) — site-wide, spans every enabled journal via sets.
Route::match(['get', 'post'], '/oai', [OaiController::class, 'handle'])->name('oai');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/journal.php';
require __DIR__.'/orcid.php';
require __DIR__.'/ror.php';
