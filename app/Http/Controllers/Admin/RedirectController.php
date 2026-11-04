<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoRedirect;
use App\Support\Audit;
use App\Support\PageBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RedirectController extends Controller
{
    public function index(): View
    {
        return view('admin.redirects.index', ['redirects' => SeoRedirect::query()->orderBy('from_path')->paginate(50)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'starts_with:/'],
            'to_url' => ['required', 'string', 'max:500'],
            'status_code' => ['required', Rule::in([301, 302])],
        ]);
        $data['from_path'] = SeoRedirect::normalizePath($data['from_path']);
        $data['to_url'] = PageBlocks::safeUrl($data['to_url']);
        if ($data['to_url'] === '') {
            throw ValidationException::withMessages(['to_url' => 'Enter a path starting with / or a full https:// link.']);
        }
        if ($data['from_path'] === '/' || SeoRedirect::normalizePath($data['to_url']) === $data['from_path'] && str_starts_with($data['to_url'], '/')) {
            throw ValidationException::withMessages(['from_path' => 'This redirect would point to itself.']);
        }
        if (SeoRedirect::query()->where('from_path', $data['from_path'])->exists()) {
            throw ValidationException::withMessages(['from_path' => 'A redirect for this path already exists.']);
        }
        $redirect = SeoRedirect::query()->create($data);
        Audit::log('redirect.created', $redirect, $data);

        return back()->with('status', 'Redirect added.');
    }

    public function destroy(SeoRedirect $redirect): RedirectResponse
    {
        $redirect->delete();
        Audit::log('redirect.deleted', $redirect, ['from_path' => $redirect->from_path]);

        return back()->with('status', 'Redirect removed.');
    }
}
