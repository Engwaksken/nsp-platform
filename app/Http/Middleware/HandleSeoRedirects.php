<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** When a GET request would 404, follow a matching redirect from the CMS instead. */
class HandleSeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET')) {
            return $response;
        }

        $redirect = SeoRedirect::query()->where('from_path', SeoRedirect::normalizePath($request->path()))->first();
        if (! $redirect) {
            return $response;
        }
        $redirect->increment('hits');

        return redirect()->to($redirect->to_url, $redirect->status_code);
    }
}
