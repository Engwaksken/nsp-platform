<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Audit;
use App\Support\PageBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $pages = Page::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->query('q').'%'))
            ->when(in_array($request->query('status'), Page::STATUSES, true), fn ($query) => $query->where('status', $request->query('status')))
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page(['status' => 'draft', 'template' => 'default', 'blocks' => []]), 'media' => $this->images()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $page = DB::transaction(function () use ($data, $request) {
            $page = new Page($data);
            $page->author_id = $request->user()->id;
            $page->updated_by = $request->user()->id;
            $page->save();
            $page->snapshot($request->user()->id, 'Created');

            return $page;
        });
        Audit::log('page.created', $page, ['status' => $page->status]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page, 'media' => $this->images()]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);
        $before = $page->only(['status', 'slug', 'published_at']);
        DB::transaction(function () use ($page, $data, $request) {
            $page->fill($data);
            $page->updated_by = $request->user()->id;
            $page->save();
            $page->snapshot($request->user()->id, $request->input('revision_note'));
        });
        Audit::log('page.updated', $page, ['before' => $before, 'after' => $page->only(['status', 'slug', 'published_at'])]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        abort_if($page->slug === 'home', 422, 'The home page cannot be deleted. Unpublish it instead.');
        $page->delete();
        Audit::log('page.deleted', $page);

        return redirect()->route('admin.pages.index')->with('status', 'Page moved to trash.');
    }

    /** Shows the current saved version, including drafts, to editors only. */
    public function preview(Page $page): View
    {
        return view('site.page', [
            'page' => $page,
            'blocks' => PageBlocks::prepare($page->blocks ?? []),
            'isHome' => $page->slug === 'home',
            'isPreview' => true,
        ]);
    }

    public function revisions(Page $page): View
    {
        return view('admin.pages.revisions', ['page' => $page, 'revisions' => $page->revisions()->with('creator')->paginate(20)]);
    }

    public function restore(Request $request, Page $page, PageRevision $revision): RedirectResponse
    {
        abort_unless($revision->page_id === $page->id, 404);
        abort_if($page->status === 'published' && ! Gate::allows('publish-content'), 403,
            'Restoring a live page unpublishes it, which needs publishing rights.');
        DB::transaction(function () use ($page, $revision, $request) {
            $page->title = $revision->title;
            $page->summary = $revision->summary;
            $page->blocks = $revision->blocks;
            $meta = $revision->meta ?? [];
            $page->seo_title = $meta['seo_title'] ?? $page->seo_title;
            $page->seo_description = $meta['seo_description'] ?? $page->seo_description;
            // Restoring content never publishes it: the page goes back to draft for review.
            $page->status = 'draft';
            $page->updated_by = $request->user()->id;
            $page->save();
            $page->snapshot($request->user()->id, 'Restored from revision #'.$revision->id);
        });
        Audit::log('page.restored', $page, ['revision_id' => $revision->id]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Revision restored as a draft.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Page::RESERVED_SLUGS), Rule::unique('pages', 'slug')->ignore($page?->id)],
            'template' => ['required', Rule::in(array_keys(Page::TEMPLATES))],
            'summary' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(Page::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_image_id' => ['nullable', 'integer', 'exists:media,id'],
            'blocks' => ['nullable', 'array', 'max:30'],
            'blocks.*.type' => ['required', Rule::in(array_keys(Page::BLOCK_TYPES))],
            'revision_note' => ['nullable', 'string', 'max:200'],
        ], ['slug.regex' => 'Use lower-case letters, numbers and single hyphens only.']);

        // Changing live content, publishing or unpublishing all need publishing rights.
        if (($data['status'] === 'published' || $page?->status === 'published') && ! Gate::allows('publish-content')) {
            abort(403, 'You can save drafts. Publishing, unpublishing or editing a live page needs publishing rights.');
        }
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = $page?->published_at ?? now();
        }
        $data['blocks'] = PageBlocks::fromInput($request->input('blocks', []));
        unset($data['revision_note']);

        return $data;
    }

    private function images()
    {
        return Media::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit(200)->get();
    }
}
