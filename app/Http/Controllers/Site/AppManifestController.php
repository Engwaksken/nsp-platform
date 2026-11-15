<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Lets phones install the site as an app ("Add to home screen"): the web app manifest,
 * built from the branding settings, and the offline page the service worker shows.
 */
class AppManifestController extends Controller
{
    public function manifest(): JsonResponse
    {
        $brand = Settings::all();
        $favicon = $brand['favicon_media_id'] ? Media::query()->find($brand['favicon_media_id']) : null;
        $name = (string) $brand['site_name'];
        $color = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $brand['primary_color']) ? $brand['primary_color'] : '#3a3a3c';
        $icon = [
            'src' => $favicon?->url() ?? asset('favicon.ico'),
            'sizes' => 'any',
            'type' => $favicon?->mime_type ?? 'image/x-icon',
        ];

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => (string) ($brand['site_tagline'] ?? ''),
            'start_url' => route('home', [], false),
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f1f3f6',
            'theme_color' => $color,
            'icons' => [$icon],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function offline(): Response
    {
        return response()->view('offline', ['brand' => Settings::all()], 200)->header('Cache-Control', 'public, max-age=3600');
    }
}
