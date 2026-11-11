<?php

namespace App\Providers;

use App\Ai\AiClient;
use App\Ai\AiSettings;
use App\Ai\AnthropicAiClient;
use App\Ai\ProviderAiClient;
use App\Contracts\StartupRegistry;
use App\Models\Media;
use App\Models\Menu;
use App\Services\Oidc\OidcClient;
use App\Services\Registry\DisabledStartupRegistry;
use App\Services\Registry\LocalStartupRegistry;
use App\Support\Features;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OidcClient::class, fn () => OidcClient::fromConfig());
        $this->app->bind(AiClient::class, function () {
            $config = AiSettings::config();

            return ($config['provider'] ?? 'anthropic') === 'anthropic'
                ? new AnthropicAiClient($config)
                : new ProviderAiClient($config);
        });
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

        // Links in emails (password reset, notifications) always use APP_URL in production, whatever Host was sent.
        if ($this->app->isProduction() && config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Performance guard: tests fail on lazy loading (N+1 queries); production only logs it.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            if ($this->app->runningUnitTests()) {
                throw new LazyLoadingViolationException($model, $relation);
            }
            Log::notice('[n+1] '.get_class($model).'::'.$relation);
        });

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
        RateLimiter::for('api-clients', fn (Request $request) => Limit::perMinute(120)->by('api|'.strtok((string) $request->bearerToken(), '.').'|'.$request->ip()));
        RateLimiter::for('oidc', fn (Request $request) => Limit::perMinute(20)->by('oidc|'.$request->ip()));

        // Branding and menus for every page view; loaded once per request.
        View::composer(['layouts.*', 'site.*', 'auth.*', 'account.*', 'workspace.*', 'admin.*'], function ($view) {
            $view->with($this->brandData());
        });
    }
}
