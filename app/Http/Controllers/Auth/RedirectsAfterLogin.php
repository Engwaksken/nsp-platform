<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/** Sends a user back to the page they wanted, but only if it is on this site. */
trait RedirectsAfterLogin
{
    protected function redirectAfterLogin(User $user): RedirectResponse
    {
        $default = $user->canUseAdmin() ? route('admin.dashboard') : route('workspace');
        $intended = (string) session()->pull('url.intended', '');

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $isLocal = $intended !== ''
            && parse_url($intended, PHP_URL_HOST) === $appHost
            && in_array(parse_url($intended, PHP_URL_SCHEME), ['http', 'https'], true);

        return redirect()->to($isLocal ? $intended : $default);
    }
}
