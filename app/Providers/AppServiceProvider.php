<?php

namespace App\Providers;

use App\Contracts\StartupRegistry;
use App\Models\Media;
use App\Models\Menu;
use App\Services\Oidc\OidcClient;
use App\Services\Registry\DisabledStartupRegistry;
use App\Services\Registry\LocalStartupRegistry;
use App\Support\Features;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OidcClient::class, fn () => OidcClient::fromConfig());
        // The registry is hosted inside NSP (decision D2); an HTTP client can replace it later.
        $this->app->bind(StartupRegistry::class, fn () => Features::enabled('registry')
            ? new LocalStartupRegistry
            : new DisabledStartupRegistry);
    }

    private function brandData(): array
    {
        return once(function () {
            $brand = Settings::all();
            $menus = Schema::hasTable('menus')
                ? Menu::query()->with('items.page')->get()->keyBy('location')
                : collect();
            $mediaIds = array_filter([$brand['logo_media_id'], $brand['favicon_media_id']]);
            $media = $mediaIds ? Media::query()->whereIn('id', $mediaIds)->get()->keyBy('id') : collect();

            return [
                'brand' => $brand,
                'brandFont' => Settings::fontStack(),
                'brandLogo' => $brand['logo_media_id'] ? $media->get($brand['logo_media_id']) : null,
                'brandFavicon' => $brand['favicon_media_id'] ? $media->get($brand['favicon_media_id']) : null,
                'headerMenu' => $menus->get('header'),
                'footerMenu' => $menus->get('footer'),
            ];
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // Super admins pass every permission check.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by('contact|'.$request->ip()),
            Limit::perDay(20)->by('contact-day|'.$request->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('pw|'.$request->ip()),
            Limit::perHour(5)->by('pw-email|'.strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('oidc', fn (Request $request) => Limit::perMinute(20)->by('oidc|'.$request->ip()));

        // Branding and menus for every page view; loaded once per request.
        View::composer(['layouts.*', 'site.*', 'auth.*', 'workspace.*', 'admin.*'], function ($view) {
            $view->with($this->brandData());
        });
    }
}
