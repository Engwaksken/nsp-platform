<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Site;
use App\Http\Controllers\Workspace;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
| Public site
*/
Route::get('/', [Site\PageController::class, 'home'])->name('home');
Route::get('/news', [Site\NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [Site\NewsController::class, 'show'])->name('news.show');
Route::get('/faqs', [Site\FaqController::class, 'index'])->name('faqs');
Route::get('/contact', [Site\ContactController::class, 'show'])->name('contact');
Route::post('/contact', [Site\ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('/sitemap.xml', [Site\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Site\SitemapController::class, 'robots'])->name('robots');

/*
| Authentication
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
    Route::get('/login/two-factor', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/login/two-factor', [MfaController::class, 'verify'])->name('mfa.verify');
    Route::middleware(['feature:oidc', 'throttle:oidc'])->group(function () {
        Route::get('/auth/oidc/redirect', [OidcController::class, 'redirect'])->name('oidc.redirect');
        Route::get('/auth/oidc/callback', [OidcController::class, 'callback'])->name('oidc.callback');
    });
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Account security (two-factor sign-in)
*/
Route::middleware('auth')->prefix('account/security')->name('mfa.')->group(function () {
    Route::get('/', [MfaController::class, 'setup'])->name('setup');
    Route::post('/enable', [MfaController::class, 'enable'])->middleware('throttle:10,1')->name('enable');
    Route::post('/recovery-codes', [MfaController::class, 'regenerateCodes'])->middleware('throttle:10,1')->name('recovery');
    Route::post('/disable', [MfaController::class, 'disable'])->middleware('throttle:10,1')->name('disable');
});

/*
| Founder / member workspace
*/
Route::get('/workspace', [WorkspaceController::class, 'index'])->middleware('auth')->name('workspace');

/*
| Startup registry (hosted in NSP; decision D2)
*/
Route::middleware(['auth', 'feature:registry'])->group(function () {
    Route::prefix('workspace/startups')->name('workspace.startups.')->group(function () {
        Route::get('/', [Workspace\StartupController::class, 'index'])->name('index');
        Route::get('/new', [Workspace\StartupController::class, 'create'])->name('create');
        Route::post('/', [Workspace\StartupController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{startup}', [Workspace\StartupController::class, 'show'])->name('show');
        Route::get('/{startup}/edit', [Workspace\StartupController::class, 'edit'])->name('edit');
        Route::put('/{startup}', [Workspace\StartupController::class, 'update'])->name('update');
        Route::post('/{startup}/switch', [Workspace\StartupController::class, 'switch'])->name('switch');
        Route::get('/{startup}/history', [Workspace\StartupController::class, 'history'])->name('history');
        Route::post('/{startup}/join', [Workspace\StartupController::class, 'requestJoin'])->middleware('throttle:10,1')->name('join');
        Route::get('/{startup}/team', [Workspace\TeamController::class, 'index'])->name('team');
        Route::post('/{startup}/team', [Workspace\TeamController::class, 'invite'])->middleware('throttle:30,1')->name('team.invite');
        Route::put('/{startup}/team/{member}', [Workspace\TeamController::class, 'updateRole'])->name('team.role');
        Route::delete('/{startup}/team/{member}', [Workspace\TeamController::class, 'remove'])->name('team.remove');
        Route::post('/{startup}/team/{member}/decision', [Workspace\TeamController::class, 'decide'])->name('team.decide');
        Route::get('/{startup}/sharing', [Workspace\ConsentController::class, 'index'])->name('consents');
        Route::put('/{startup}/sharing/{purpose}', [Workspace\ConsentController::class, 'update'])->name('consents.update');
    });
    Route::get('/invitations/{token}', [Workspace\InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}/accept', [Workspace\InvitationController::class, 'accept'])->middleware('throttle:20,1')->name('invitations.accept');
    Route::post('/invitations/{token}/decline', [Workspace\InvitationController::class, 'decline'])->name('invitations.decline');
});

/*
| Admin CMS
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'mfa'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:manage-pages')->group(function () {
        Route::get('pages/{page}/preview', [Admin\PageController::class, 'preview'])->name('pages.preview');
        Route::get('pages/{page}/revisions', [Admin\PageController::class, 'revisions'])->name('pages.revisions');
        Route::post('pages/{page}/revisions/{revision}/restore', [Admin\PageController::class, 'restore'])->name('pages.restore');
        Route::resource('pages', Admin\PageController::class)->except('show');
    });

    Route::middleware('can:manage-posts')->group(function () {
        Route::resource('posts', Admin\PostController::class)->except('show');
        Route::get('categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    Route::middleware('can:manage-media')->group(function () {
        Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
        Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
        Route::patch('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
    });

    Route::middleware('can:manage-menus')->group(function () {
        Route::get('menus', [Admin\MenuController::class, 'index'])->name('menus.index');
        Route::post('menus/{menu}/items', [Admin\MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('menus/{menu}/items/{item}', [Admin\MenuController::class, 'updateItem'])->name('menus.items.update')->scopeBindings();
        Route::delete('menus/{menu}/items/{item}', [Admin\MenuController::class, 'destroyItem'])->name('menus.items.destroy')->scopeBindings();
    });

    Route::middleware('can:manage-site-content')->group(function () {
        Route::get('content/{type}', [Admin\SiteContentController::class, 'index'])->name('content.index');
        Route::get('content/{type}/create', [Admin\SiteContentController::class, 'create'])->name('content.create');
        Route::post('content/{type}', [Admin\SiteContentController::class, 'store'])->name('content.store');
        Route::get('content/{type}/{id}/edit', [Admin\SiteContentController::class, 'edit'])->name('content.edit');
        Route::put('content/{type}/{id}', [Admin\SiteContentController::class, 'update'])->name('content.update');
        Route::delete('content/{type}/{id}', [Admin\SiteContentController::class, 'destroy'])->name('content.destroy');
    });

    Route::middleware('can:manage-redirects')->group(function () {
        Route::get('redirects', [Admin\RedirectController::class, 'index'])->name('redirects.index');
        Route::post('redirects', [Admin\RedirectController::class, 'store'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [Admin\RedirectController::class, 'destroy'])->name('redirects.destroy');
    });

    Route::middleware('can:manage-contact-messages')->group(function () {
        Route::get('messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [Admin\ContactMessageController::class, 'show'])->name('messages.show');
        Route::patch('messages/{message}', [Admin\ContactMessageController::class, 'update'])->name('messages.update');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/reset-mfa', [Admin\UserController::class, 'resetMfa'])->name('users.reset-mfa');
    });

    Route::middleware('can:manage-feature-flags')->group(function () {
        Route::get('features', [Admin\FeatureFlagController::class, 'index'])->name('features.index');
        Route::put('features/{key}', [Admin\FeatureFlagController::class, 'update'])->name('features.update');
    });

    Route::get('audit', [Admin\AuditLogController::class, 'index'])->middleware('can:view-audit-log')->name('audit.index');

    Route::middleware('can:manage-startups')->group(function () {
        Route::get('startups', [Admin\StartupController::class, 'index'])->name('startups.index');
        Route::get('startups/{startup}', [Admin\StartupController::class, 'show'])->name('startups.show');
        Route::put('startups/{startup}/status', [Admin\StartupController::class, 'updateStatus'])->name('startups.status');
        Route::post('startups/{startup}/aliases', [Admin\StartupController::class, 'addAlias'])->name('startups.aliases');
    });
    Route::middleware('can:review-duplicates')->group(function () {
        Route::get('duplicates', [Admin\DuplicateController::class, 'index'])->name('duplicates.index');
        Route::post('duplicates/scan', [Admin\DuplicateController::class, 'scan'])->name('duplicates.scan');
        Route::post('duplicates/{candidate}', [Admin\DuplicateController::class, 'decide'])->name('duplicates.decide');
        Route::post('merges/{merge}/undo', [Admin\DuplicateController::class, 'undo'])->name('merges.undo');
    });
});

/*
| CMS pages: keep last so application routes win.
*/
Route::get('/{slug}', [Site\PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page.show');
