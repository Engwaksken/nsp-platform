<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\NotificationController;
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
Route::get('/manifest.webmanifest', [Site\AppManifestController::class, 'manifest'])->name('app.manifest');
Route::get('/offline', [Site\AppManifestController::class, 'offline'])->name('app.offline');

/*
| Opportunities and events (Phase 5a)
*/
Route::middleware('feature:opportunities')->prefix('opportunities')->name('opportunities.')->group(function () {
    Route::get('/', [Site\OpportunityController::class, 'index'])->name('index');
    Route::middleware('auth')->group(function () {
        Route::get('/submit', [Site\OpportunityController::class, 'create'])->name('create');
        Route::post('/', [Site\OpportunityController::class, 'store'])->middleware('throttle:10,60')->name('store');
        Route::post('/{opportunity}/bookmark', [Site\OpportunityController::class, 'bookmark'])->name('bookmark');
        Route::post('/{opportunity}/register', [Site\OpportunityController::class, 'register'])->middleware('throttle:20,1')->name('register');
        Route::post('/{opportunity}/withdraw', [Site\OpportunityController::class, 'withdraw'])->name('withdraw');
    });
    Route::get('/{opportunity}', [Site\OpportunityController::class, 'show'])->name('show');
});

/*
| Jobs board (Phase 5d)
*/
Route::middleware('feature:talent')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [Site\JobController::class, 'index'])->name('index');
    Route::get('/{vacancy}', [Site\JobController::class, 'show'])->name('show');
    Route::post('/{vacancy}/apply', [Site\JobController::class, 'apply'])->middleware(['auth', 'throttle:20,60'])->name('apply');
});

/*
| Marketplace and startup showcase (Phase 5e)
*/
Route::middleware('feature:marketplace')->group(function () {
    Route::get('/startups', [Site\StartupDirectoryController::class, 'index'])->name('directory.index');
    Route::get('/startups/{startup}', [Site\StartupDirectoryController::class, 'show'])->name('directory.show');
    Route::prefix('marketplace')->name('marketplace.')->group(function () {
        Route::get('/', [Site\MarketplaceController::class, 'index'])->name('index');
        Route::middleware('auth')->group(function () {
            Route::get('/messages', [Site\MarketplaceController::class, 'inbox'])->name('inbox');
            Route::get('/messages/{inquiry}', [Site\MarketplaceController::class, 'conversation'])->name('inquiries.show');
            Route::post('/messages/{inquiry}', [Site\MarketplaceController::class, 'reply'])->middleware('throttle:60,60')->name('inquiries.reply');
            Route::post('/messages/{inquiry}/close', [Site\MarketplaceController::class, 'close'])->name('inquiries.close');
            Route::post('/messages/{inquiry}/report', [Site\MarketplaceController::class, 'reportInquiry'])->middleware('throttle:10,60')->name('inquiries.report');
            Route::post('/{listing}/inquire', [Site\MarketplaceController::class, 'inquire'])->middleware('throttle:10,60')->name('inquire');
            Route::post('/{listing}/report', [Site\MarketplaceController::class, 'reportListing'])->middleware('throttle:10,60')->name('report');
        });
        Route::get('/{listing}', [Site\MarketplaceController::class, 'show'])->name('show');
    });
});

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
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
});

/*
| My profile (name, email address, password)
*/
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('edit');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->middleware('throttle:10,1')->name('profile.update');
    Route::put('/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/email/confirm/{hash}', [AccountController::class, 'confirmEmail'])->middleware(['signed', 'throttle:6,1'])->name('email.confirm');
    Route::delete('/email/pending', [AccountController::class, 'cancelEmail'])->name('email.cancel');
});

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
        Route::get('/{startup}/overview', [Workspace\OverviewController::class, 'index'])->name('overview');
        Route::get('/{startup}/export', [Workspace\OverviewController::class, 'export'])->middleware('throttle:10,60')->name('export');
        Route::post('/{startup}/join', [Workspace\StartupController::class, 'requestJoin'])->middleware('throttle:10,1')->name('join');
        Route::get('/{startup}/team', [Workspace\TeamController::class, 'index'])->name('team');
        Route::post('/{startup}/team', [Workspace\TeamController::class, 'invite'])->middleware('throttle:30,1')->name('team.invite');
        Route::put('/{startup}/team/{member}', [Workspace\TeamController::class, 'updateRole'])->name('team.role');
        Route::delete('/{startup}/team/{member}', [Workspace\TeamController::class, 'remove'])->name('team.remove');
        Route::post('/{startup}/team/{member}/decision', [Workspace\TeamController::class, 'decide'])->name('team.decide');
        Route::get('/{startup}/sharing', [Workspace\ConsentController::class, 'index'])->name('consents');
        Route::put('/{startup}/sharing/{purpose}', [Workspace\ConsentController::class, 'update'])->name('consents.update');
    });
    Route::middleware('feature:vgi')->prefix('workspace/startups/{startup}/growth')->name('workspace.growth')->group(function () {
        Route::get('/', [Workspace\GrowthController::class, 'index'])->name('');
        Route::post('/assessments', [Workspace\GrowthController::class, 'start'])->name('.start');
        Route::get('/assessments/{assessment}', [Workspace\GrowthController::class, 'show'])->name('.assessment');
        Route::post('/assessments/{assessment}/guidance/{dimension}/feedback', [Workspace\GrowthController::class, 'guidanceFeedback'])->name('.guidance.feedback');
        Route::put('/assessments/{assessment}', [Workspace\GrowthController::class, 'update'])->name('.save');
        Route::get('/assessments/{assessment}/report', [Workspace\GrowthController::class, 'report'])->name('.report');
        Route::post('/actions', [Workspace\GrowthActionController::class, 'store'])->name('.actions.store');
        Route::put('/actions/{action}', [Workspace\GrowthActionController::class, 'update'])->name('.actions.update');
        Route::delete('/actions/{action}', [Workspace\GrowthActionController::class, 'destroy'])->name('.actions.destroy');
    });
    Route::middleware('feature:opportunities')->group(function () {
        Route::get('/workspace/startups/{startup}/opportunities', [Workspace\OpportunityController::class, 'index'])->name('workspace.opportunities');
        Route::post('/workspace/startups/{startup}/opportunities/{opportunity}/feedback', [Workspace\OpportunityController::class, 'feedback'])->name('workspace.opportunities.feedback');
    });
    Route::middleware('feature:funding')->prefix('workspace/startups/{startup}/funding')->name('workspace.funding')->group(function () {
        Route::get('/', [Workspace\FundingController::class, 'index'])->name('');
        Route::put('/request', [Workspace\FundingController::class, 'saveRequest'])->name('.request');
        Route::post('/request/close', [Workspace\FundingController::class, 'closeRequest'])->name('.close');
        Route::post('/files', [Workspace\FundingController::class, 'upload'])->middleware('throttle:30,1')->name('.files.store');
        Route::delete('/files/{file}', [Workspace\FundingController::class, 'destroyFile'])->name('.files.destroy');
        Route::post('/grants', [Workspace\FundingController::class, 'grant'])->name('.grants.store');
        Route::delete('/grants/{grant}', [Workspace\FundingController::class, 'revoke'])->name('.grants.destroy');
        Route::post('/introductions', [Workspace\FundingController::class, 'introduce'])->middleware('throttle:20,60')->name('.introductions.store');
        Route::post('/introductions/{introduction}/decision', [Workspace\FundingController::class, 'decide'])->name('.introductions.decide');
    });
    Route::middleware('feature:experts')->group(function () {
        Route::get('/workspace/startups/{startup}/experts', [Workspace\ExpertDirectoryController::class, 'index'])->name('workspace.experts');
        Route::post('/workspace/startups/{startup}/experts', [Workspace\ExpertDirectoryController::class, 'request'])->middleware('throttle:20,60')->name('workspace.experts.request');
    });
    Route::middleware('feature:talent')->prefix('workspace/startups/{startup}/talent')->name('workspace.talent')->group(function () {
        Route::get('/', [Workspace\TalentController::class, 'index'])->name('');
        Route::post('/vacancies', [Workspace\TalentController::class, 'store'])->middleware('throttle:30,60')->name('.store');
        Route::get('/vacancies/{vacancy}/edit', [Workspace\TalentController::class, 'edit'])->name('.edit');
        Route::put('/vacancies/{vacancy}', [Workspace\TalentController::class, 'update'])->name('.update');
        Route::post('/vacancies/{vacancy}/status', [Workspace\TalentController::class, 'setStatus'])->name('.status');
        Route::get('/vacancies/{vacancy}', [Workspace\TalentController::class, 'pipeline'])->name('.vacancy');
        Route::get('/vacancies/{vacancy}/applications.csv', [Workspace\TalentController::class, 'exportApplications'])->middleware('throttle:20,60')->name('.export');
        Route::get('/applications/{application}', [Workspace\TalentController::class, 'application'])->name('.application');
        Route::put('/applications/{application}', [Workspace\TalentController::class, 'move'])->name('.move');
        Route::post('/applications/{application}/interviews', [Workspace\TalentController::class, 'scheduleInterview'])->name('.interviews.store');
        Route::put('/interviews/{interview}', [Workspace\TalentController::class, 'updateInterview'])->name('.interviews.update');
        Route::get('/applications/{application}/cv', [Workspace\TalentController::class, 'cv'])->middleware('throttle:60,1')->name('.cv');
        Route::get('/candidates', [Workspace\TalentController::class, 'search'])->name('.search');
        Route::post('/candidates/invite', [Workspace\TalentController::class, 'invite'])->middleware('throttle:30,60')->name('.invite');
    });
    Route::middleware('feature:marketplace')->prefix('workspace/startups/{startup}/marketplace')->name('workspace.marketplace')->group(function () {
        Route::get('/', [Workspace\ListingController::class, 'index'])->name('');
        Route::post('/listings', [Workspace\ListingController::class, 'store'])->middleware('throttle:30,60')->name('.store');
        Route::get('/listings/{listing}/edit', [Workspace\ListingController::class, 'edit'])->name('.edit');
        Route::put('/listings/{listing}', [Workspace\ListingController::class, 'update'])->name('.update');
        Route::post('/listings/{listing}/status', [Workspace\ListingController::class, 'setStatus'])->name('.status');
    });
    Route::middleware('feature:ai_assistant')->prefix('workspace/startups/{startup}/assistant')->name('workspace.assistant')->group(function () {
        Route::get('/', [Workspace\AssistantController::class, 'show'])->name('');
        Route::post('/', [Workspace\AssistantController::class, 'run'])->middleware('throttle:6,1')->name('.run');
        Route::post('/consent', [Workspace\AssistantController::class, 'consent'])->name('.consent');
    });
    Route::get('/workspace/startups/{startup}/programs', [Workspace\ProgramController::class, 'index'])->middleware('feature:ims_integration')->name('workspace.startups.programs');
    Route::middleware(['feature:ims_integration', 'throttle:30,1'])->group(function () {
        Route::get('/connect/{clientId}', [Workspace\ConnectController::class, 'show'])->name('connect.show');
        Route::post('/connect/{clientId}', [Workspace\ConnectController::class, 'decide'])->name('connect.decide');
    });
    Route::get('/invitations/{token}', [Workspace\InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}/accept', [Workspace\InvitationController::class, 'accept'])->middleware('throttle:20,1')->name('invitations.accept');
    Route::post('/invitations/{token}/decline', [Workspace\InvitationController::class, 'decline'])->name('invitations.decline');
});

/*
| Investors and data rooms (Phase 5b)
*/
Route::middleware(['auth', 'feature:funding'])->group(function () {
    Route::prefix('investors')->name('investor.')->group(function () {
        Route::get('/', [InvestorController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [InvestorController::class, 'apply'])->name('apply');
        Route::put('/profile', [InvestorController::class, 'saveProfile'])->middleware('throttle:10,1')->name('apply.store');
        Route::get('/startups/{startup}', [InvestorController::class, 'startup'])->name('startup');
        Route::post('/startups/{startup}/interest', [InvestorController::class, 'interest'])->middleware('throttle:20,60')->name('interest');
        Route::put('/introductions/{introduction}', [InvestorController::class, 'pipeline'])->name('pipeline');
        Route::post('/introductions/{introduction}/decision', [InvestorController::class, 'decide'])->name('decide');
    });
    Route::get('/data-room/{file}', [InvestorController::class, 'download'])->middleware('throttle:60,1')->name('dataroom.download');
});

/*
| Experts, mentors and engagements (Phase 5c)
*/
Route::middleware(['auth', 'feature:experts'])->group(function () {
    Route::get('/experts', [ExpertController::class, 'dashboard'])->name('experts.dashboard');
    Route::get('/experts/profile', [ExpertController::class, 'profile'])->name('experts.profile');
    Route::put('/experts/profile', [ExpertController::class, 'saveProfile'])->middleware('throttle:10,1')->name('experts.profile.store');
    Route::prefix('engagements/{engagement}')->name('engagements.')->group(function () {
        Route::get('/', [EngagementController::class, 'show'])->name('show');
        Route::post('/decision', [EngagementController::class, 'decide'])->name('decide');
        Route::post('/sessions', [EngagementController::class, 'schedule'])->middleware('throttle:30,1')->name('sessions.store');
        Route::put('/sessions/{session}', [EngagementController::class, 'updateSession'])->name('sessions.update');
        Route::post('/actions', [EngagementController::class, 'addAction'])->middleware('throttle:60,1')->name('actions.store');
        Route::post('/actions/{action}/toggle', [EngagementController::class, 'toggleAction'])->name('actions.toggle');
        Route::post('/complete', [EngagementController::class, 'complete'])->name('complete');
        Route::post('/cancel', [EngagementController::class, 'cancel'])->name('cancel');
        Route::post('/review', [EngagementController::class, 'review'])->name('review');
    });
});

/*
| Candidates (Phase 5d)
*/
Route::middleware(['auth', 'feature:talent'])->prefix('talent')->name('talent.')->group(function () {
    Route::get('/', [CandidateController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [CandidateController::class, 'profile'])->name('profile');
    Route::put('/profile', [CandidateController::class, 'saveProfile'])->middleware('throttle:10,1')->name('profile.store');
    Route::delete('/profile', [CandidateController::class, 'deleteProfile'])->name('profile.destroy');
    Route::get('/profile/cv', [CandidateController::class, 'cv'])->name('cv');
    Route::post('/applications/{application}/withdraw', [CandidateController::class, 'withdraw'])->name('withdraw');
    Route::post('/interviews/{interview}/response', [CandidateController::class, 'respondToInterview'])->name('interviews.respond');
});

/*
| Notifications (Phase 6a)
*/
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
    Route::put('/preferences', [NotificationController::class, 'savePreferences'])->name('preferences.save');
    Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
    Route::get('/{notification}', [NotificationController::class, 'open'])->whereNumber('notification')->name('open');
});
Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('/notifications/unsubscribe/{user}/{type}', [NotificationController::class, 'unsubscribe'])->name('notifications.unsubscribe');
    Route::post('/notifications/unsubscribe/{user}/{type}', [NotificationController::class, 'confirmUnsubscribe'])->name('notifications.unsubscribe.confirm');
});

Route::get('/health', [HealthController::class, 'endpoint'])->middleware('throttle:30,1')->name('health');

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
        Route::post('users/{user}/send-verification', [Admin\UserController::class, 'resendVerification'])->name('users.send-verification');
        Route::post('users/{user}/reset-mfa', [Admin\UserController::class, 'resetMfa'])->name('users.reset-mfa');
    });

    Route::middleware('can:manage-feature-flags')->group(function () {
        Route::get('features', [Admin\FeatureFlagController::class, 'index'])->name('features.index');
        Route::put('features/{key}', [Admin\FeatureFlagController::class, 'update'])->name('features.update');
        Route::get('ai', [Admin\AiSettingsController::class, 'edit'])->name('ai.edit');
        Route::put('ai', [Admin\AiSettingsController::class, 'update'])->name('ai.update');
        Route::post('ai/test', [Admin\AiSettingsController::class, 'test'])->middleware('throttle:10,1')->name('ai.test');
        Route::post('ai/toggle', [Admin\AiSettingsController::class, 'toggle'])->name('ai.toggle');
        Route::post('ai/legal', [Admin\AiSettingsController::class, 'approveLegal'])->name('ai.legal.approve');
        Route::delete('ai/legal', [Admin\AiSettingsController::class, 'withdrawLegal'])->name('ai.legal.withdraw');
    });

    Route::get('audit', [Admin\AuditLogController::class, 'index'])->middleware('can:view-audit-log')->name('audit.index');

    Route::middleware('can:manage-startups')->group(function () {
        Route::get('startups', [Admin\StartupController::class, 'index'])->name('startups.index');
        Route::get('startups/{startup}', [Admin\StartupController::class, 'show'])->name('startups.show');
        Route::put('startups/{startup}/status', [Admin\StartupController::class, 'updateStatus'])->name('startups.status');
        Route::post('startups/{startup}/aliases', [Admin\StartupController::class, 'addAlias'])->name('startups.aliases');
    });
    Route::middleware(['can:manage-integrations', 'feature:ims_integration'])->group(function () {
        Route::get('integrations', [Admin\IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('integrations/deliveries/{delivery}/replay', [Admin\IntegrationController::class, 'replay'])->name('integrations.replay');
    });
    Route::middleware(['can:manage-opportunities', 'feature:opportunities'])->prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/', [Admin\OpportunityController::class, 'index'])->name('index');
        Route::get('/create', [Admin\OpportunityController::class, 'create'])->name('create');
        Route::post('/', [Admin\OpportunityController::class, 'store'])->name('store');
        Route::get('/{opportunity}/edit', [Admin\OpportunityController::class, 'edit'])->name('edit');
        Route::put('/{opportunity}', [Admin\OpportunityController::class, 'update'])->name('update');
        Route::post('/{opportunity}/moderate', [Admin\OpportunityController::class, 'moderate'])->name('moderate');
        Route::get('/{opportunity}/registrations', [Admin\OpportunityController::class, 'interests'])->name('interests');
        Route::get('/{opportunity}/registrations.csv', [Admin\OpportunityController::class, 'export'])->name('export');
        Route::put('/{opportunity}/registrations/{interest}', [Admin\OpportunityController::class, 'updateInterest'])->name('interests.update');
    });
    Route::middleware('feature:vgi')->prefix('vgi')->name('vgi.')->group(function () {
        Route::get('/', [Admin\VgiController::class, 'index'])->name('index');
        Route::post('/frameworks', [Admin\VgiController::class, 'createDraft'])->name('create');
        Route::get('/frameworks/{framework}', [Admin\VgiController::class, 'edit'])->name('edit');
        Route::put('/frameworks/{framework}', [Admin\VgiController::class, 'update'])->name('update');
        Route::post('/frameworks/{framework}/approve', [Admin\VgiController::class, 'approve'])->name('approve');
        Route::get('/assessments/{assessment}', [Admin\VgiController::class, 'showAssessment'])->name('assessment');
        Route::post('/assessments/{assessment}/override', [Admin\VgiController::class, 'override'])->name('override');
    });
    Route::middleware(['can:verify-investors', 'feature:funding'])->group(function () {
        Route::get('investors', [Admin\InvestorController::class, 'index'])->name('investors.index');
        Route::put('investors/{investor}', [Admin\InvestorController::class, 'decide'])->name('investors.decide');
    });
    Route::middleware(['can:manage-experts', 'feature:experts'])->group(function () {
        Route::get('experts', [Admin\ExpertController::class, 'index'])->name('experts.index');
        Route::put('experts/{expert}', [Admin\ExpertController::class, 'decide'])->name('experts.decide');
        Route::get('engagements', [Admin\ExpertController::class, 'engagements'])->name('engagements.index');
        Route::post('engagements', [Admin\ExpertController::class, 'assign'])->name('engagements.assign');
    });
    Route::middleware(['can:manage-talent', 'feature:talent'])->group(function () {
        Route::get('vacancies', [Admin\VacancyController::class, 'index'])->name('vacancies.index');
        Route::post('vacancies/{vacancy}/moderate', [Admin\VacancyController::class, 'moderate'])->name('vacancies.moderate');
    });
    Route::middleware(['can:moderate-marketplace', 'feature:marketplace'])->prefix('marketplace')->name('marketplace.')->group(function () {
        Route::get('/', [Admin\MarketplaceController::class, 'index'])->name('index');
        Route::get('/reports/{report}', [Admin\MarketplaceController::class, 'report'])->name('report');
        Route::post('/reports/{report}', [Admin\MarketplaceController::class, 'resolve'])->name('resolve');
        Route::post('/listings/{listing}/restore', [Admin\MarketplaceController::class, 'restore'])->name('restore');
    });
    Route::middleware('can:manage-communications')->prefix('communications')->name('communications.')->group(function () {
        Route::get('/', [Admin\CommunicationController::class, 'index'])->name('index');
        Route::post('/announcements', [Admin\CommunicationController::class, 'store'])->name('store');
        Route::post('/announcements/{announcement}/send', [Admin\CommunicationController::class, 'send'])->name('send');
        Route::delete('/announcements/{announcement}', [Admin\CommunicationController::class, 'destroy'])->name('destroy');
    });
    Route::middleware('can:view-insights')->prefix('insights')->name('insights.')->group(function () {
        Route::get('/', [Admin\InsightsController::class, 'index'])->name('index');
        Route::get('/kpis.csv', [Admin\InsightsController::class, 'exportKpis'])->middleware('throttle:20,60')->name('export');
        Route::get('/startups.csv', [Admin\InsightsController::class, 'exportStartups'])->middleware('throttle:10,60')->name('startups');
    });
    Route::get('system', [HealthController::class, 'admin'])->middleware('can:view-system-health')->name('system');
    Route::middleware('can:review-duplicates')->group(function () {
        Route::get('duplicates', [Admin\DuplicateController::class, 'index'])->name('duplicates.index');
        Route::post('duplicates/scan', [Admin\DuplicateController::class, 'scan'])->name('duplicates.scan');
        Route::post('duplicates/{candidate}', [Admin\DuplicateController::class, 'decide'])->name('duplicates.decide');
        Route::post('merges/{merge}/undo', [Admin\DuplicateController::class, 'undo'])->name('merges.undo');
    });
});

/*
| Owner-alignment modules: one route file per module in routes/modules/
| (organizations, builder, challenges, knowledge, intelligence, dashboards ...).
| Loaded before the CMS catch-all so module routes win.
*/
foreach (glob(__DIR__.'/modules/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}

/*
| CMS pages: keep last so application routes win.
*/
Route::get('/{slug}', [Site\PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page.show');
