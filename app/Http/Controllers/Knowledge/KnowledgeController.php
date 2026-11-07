<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Knowledge\KnowledgeAccess;
use App\Knowledge\KnowledgeService;
use App\Knowledge\Recommender;
use App\Knowledge\Search\SearchFactory;
use App\Models\Knowledge\KnowledgeBookmark;
use App\Models\Knowledge\KnowledgeCategory;
use App\Models\Knowledge\KnowledgeRecentView;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Knowledge\KnowledgeTag;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function __construct(private KnowledgeService $knowledge, private Recommender $recommender) {}

    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();
        $filters = $this->filters($request);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $search = SearchFactory::make();
        $resources = $search->search($user, $q, $filters, 12);

        $bookmarked = $user ? KnowledgeBookmark::query()->where('user_id', $user->id)->pluck('knowledge_resource_id')->all() : [];
        $showRecommendations = $user && $q === '' && ! array_filter($filters) && $resources->currentPage() === 1;

        return view('knowledge.index', [
            'resources' => $resources,
            'q' => $q,
            'filters' => $filters,
            'engine' => $search->engine(),
            'categories' => KnowledgeCategory::tree(),
            'tags' => KnowledgeTag::query()->whereHas('resources', fn ($r) => $r->live()->visibleTo($user))->orderBy('name')->limit(40)->get(),
            'bookmarked' => $bookmarked,
            'recommendations' => $showRecommendations ? $this->recommender->recommend($user, 3) : collect(),
        ]);
    }

    public function show(Request $request, KnowledgeResource $resource): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($redirect = $this->authorizeView($request, $resource)) {
            return $redirect;
        }
        $resource->load('category', 'tags');
        if ($user) {
            $this->knowledge->recordView($user, $resource);
        }

        return view('knowledge.show', [
            'resource' => $resource,
            'bodyHtml' => Markdown::render($resource->body),
            'related' => $this->related($user, $resource),
            'reasons' => $this->recommender->reasonsFor($user, $resource),
            'isBookmarked' => $user && KnowledgeBookmark::query()->where('user_id', $user->id)->where('knowledge_resource_id', $resource->id)->exists(),
        ]);
    }

    public function download(Request $request, KnowledgeResource $resource)
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($redirect = $this->authorizeView($request, $resource)) {
            return $redirect;
        }

        if ($resource->hasFile()) {
            $disk = Storage::disk($resource->file_disk ?: config('knowledge.uploads.disk', 'local'));
            abort_unless($disk->exists($resource->file_path), 404);
            $this->knowledge->recordDownload($user, $resource, 'file', $request->ip());

            return $disk->download($resource->file_path, $resource->file_name ?: $resource->slug, [
                'Content-Type' => $resource->file_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        abort_unless($resource->external_url, 404);
        $this->knowledge->recordDownload($user, $resource, 'link', $request->ip());

        return redirect()->away($resource->external_url)->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function bookmark(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        abort_unless(KnowledgeAccess::canView($request->user(), $resource), 404);
        $saved = $this->knowledge->toggleBookmark($request->user(), $resource);

        return back()->with('status', $saved ? 'Saved to your knowledge list.' : 'Removed from your saved resources.');
    }

    public function mine(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $visible = fn ($q) => $q->live()->visibleTo($user);

        return view('knowledge.mine', [
            'bookmarks' => KnowledgeBookmark::query()->where('user_id', $user->id)->whereHas('resource', $visible)
                ->with('resource.category')->latest('id')->get(),
            'recent' => KnowledgeRecentView::query()->where('user_id', $user->id)->whereHas('resource', $visible)
                ->with('resource.category')->orderByDesc('viewed_at')->limit((int) config('knowledge.recent_views_kept', 20))->get(),
            'recommendations' => $this->recommender->recommend($user, 6),
        ]);
    }

    /**
     * Not live, or staff-only for non-staff, or startup-only for signed-in non-startup users: 404.
     * Members/startup resources for guests: sign in first.
     */
    private function authorizeView(Request $request, KnowledgeResource $resource): ?RedirectResponse
    {
        $user = $request->user();
        if (KnowledgeAccess::canView($user, $resource)) {
            return null;
        }
        abort_unless($resource->isLive(), 404);
        if (! $user && in_array($resource->visibility, ['members', 'startups'], true)) {
            return redirect()->guest(route('login'));
        }
        abort(404);
    }

    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($v = (string) $request->query($key, ''), $allowed, true) ? $v : null;

        return array_filter([
            'type' => $pick('type', array_keys(config('knowledge.resource_types'))),
            'audience' => $pick('audience', array_keys(config('knowledge.audiences'))),
            'stage' => $pick('stage', array_keys(config('knowledge.stages'))),
            'category' => ctype_digit((string) $request->query('category')) ? (int) $request->query('category') : null,
            'tag' => preg_match('/^[a-z0-9-]{1,60}$/', (string) $request->query('tag')) ? (string) $request->query('tag') : null,
        ]);
    }

    /** Up to four visible resources sharing tags or category, each with why it is related. */
    private function related(?User $user, KnowledgeResource $resource): array
    {
        $tagIds = $resource->tags->pluck('id')->all();
        if (! $tagIds && ! $resource->category_id) {
            return [];
        }
        $candidates = KnowledgeResource::query()->live()->visibleTo($user)->whereKeyNot($resource->id)
            ->where(function ($q) use ($tagIds, $resource) {
                if ($tagIds) {
                    $q->whereHas('tags', fn ($t) => $t->whereIn('knowledge_tags.id', $tagIds));
                }
                if ($resource->category_id) {
                    $q->orWhere('category_id', $resource->category_id);
                }
            })
            ->with('tags', 'category')->latest('published_at')->limit(20)->get();

        return $candidates->map(function (KnowledgeResource $other) use ($tagIds, $resource) {
            $shared = $other->tags->whereIn('id', $tagIds)->pluck('name')->all();
            $why = [];
            if ($shared) {
                $why[] = 'Shares '.(count($shared) === 1 ? 'tag' : 'tags').': '.implode(', ', $shared);
            }
            if ($resource->category_id && $other->category_id === $resource->category_id) {
                $why[] = 'Same category: '.$resource->category->name;
            }

            return ['resource' => $other, 'why' => implode(' · ', $why), 'weight' => count($shared) * 2 + ($why ? 1 : 0)];
        })->sortByDesc('weight')->take(4)->values()->all();
    }
}
