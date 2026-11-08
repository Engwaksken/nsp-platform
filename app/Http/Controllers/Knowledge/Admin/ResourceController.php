<?php

namespace App\Http\Controllers\Knowledge\Admin;

use App\Http\Controllers\Controller;
use App\Knowledge\KnowledgeService;
use App\Models\Knowledge\KnowledgeCategory;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Knowledge\KnowledgeTag;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Knowledge Hub editorial workflow: draft -> in review -> published -> archived, with versions. */
class ResourceController extends Controller
{
    public function __construct(private KnowledgeService $knowledge) {}

    public function index(Request $request): View
    {
        $this->editorOrReviewer($request);
        $status = in_array($request->query('status'), array_keys(KnowledgeResource::STATUSES), true) ? $request->query('status') : null;
        $resources = KnowledgeResource::query()->with('category', 'uploader')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('due') === '1', fn ($q) => $q->whereNotNull('review_due_at')->where('review_due_at', '<=', now()))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.mb_substr((string) $request->query('q'), 0, 100).'%'))
            ->latest('updated_at')->paginate(25)->withQueryString();

        return view('admin.knowledge.index', [
            'resources' => $resources,
            'status' => $status,
            'counts' => KnowledgeResource::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage-knowledge'), 403);

        return view('admin.knowledge.form', $this->formData(new KnowledgeResource([
            'visibility' => 'public', 'license' => 'hive-owned', 'resource_type' => 'practical_guide',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage-knowledge'), 403);
        $resource = new KnowledgeResource;
        $resource->uuid = (string) Str::uuid();
        $this->fill($request, $resource);
        $resource->status = 'draft';
        $resource->uploaded_by = $request->user()->id;
        DB::transaction(function () use ($request, $resource) {
            $resource->save();
            $resource->tags()->sync(KnowledgeTag::idsFromString($request->input('tags')));
        });
        Audit::log('knowledge.created', $resource, ['title' => $resource->title]);

        return redirect()->route('admin.knowledge.edit', $resource->uuid)->with('status', 'Draft saved.');
    }

    public function edit(Request $request, KnowledgeResource $resource): View
    {
        $this->editorOrReviewer($request);

        return view('admin.knowledge.form', $this->formData($resource->load('tags', 'uploader', 'reviewer')) + [
            'mayPublish' => $this->knowledge->mayPublish($resource, $request->user()),
        ]);
    }

    public function update(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('manage-knowledge'), 403);
        $live = in_array($resource->status, ['published', 'archived'], true);
        abort_if($live && ! $user->can('publish-knowledge'), 403, 'Changing published content needs a reviewer with publishing rights.');

        $this->fill($request, $resource);
        DB::transaction(function () use ($request, $resource, $user) {
            $resource->save();
            $resource->tags()->sync(KnowledgeTag::idsFromString($request->input('tags')));
            if ($resource->status === 'published') {
                $this->knowledge->snapshot($resource->fresh(), $user, mb_substr((string) $request->input('change_note', 'Edited while published'), 0, 500) ?: 'Edited while published');
            }
        });
        Audit::log('knowledge.updated', $resource, ['title' => $resource->title, 'status' => $resource->status]);

        return redirect()->route('admin.knowledge.edit', $resource->uuid)->with('status', 'Changes saved.');
    }

    public function submit(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        abort_unless($request->user()->can('manage-knowledge'), 403);
        $this->knowledge->submit($resource, $request->user());

        return back()->with('status', 'Sent for review.');
    }

    public function publish(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        abort_unless($request->user()->can('publish-knowledge'), 403);
        $note = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']])['review_note'] ?? null;
        $version = $this->knowledge->publish($resource, $request->user(), $note);

        return back()->with('status', 'Published as version '.$version->version.'.');
    }

    public function returnToDraft(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        abort_unless($request->user()->can('publish-knowledge'), 403);
        $note = $request->validate(['review_note' => ['required', 'string', 'max:1000']])['review_note'];
        $this->knowledge->returnToDraft($resource, $request->user(), $note);

        return back()->with('status', 'Returned to the editors as a draft.');
    }

    public function archive(Request $request, KnowledgeResource $resource): RedirectResponse
    {
        abort_unless($request->user()->can('publish-knowledge'), 403);
        $this->knowledge->archive($resource, $request->user());

        return back()->with('status', 'Archived. It no longer appears on the site.');
    }

    public function versions(Request $request, KnowledgeResource $resource): View
    {
        $this->editorOrReviewer($request);

        return view('admin.knowledge.versions', ['resource' => $resource, 'versions' => $resource->versions()->with('publisher')->get()]);
    }

    /** Lets editors and reviewers open the attached file of any status (e.g. while reviewing a draft). */
    public function file(Request $request, KnowledgeResource $resource)
    {
        $this->editorOrReviewer($request);
        abort_unless($resource->hasFile(), 404);
        $disk = Storage::disk($resource->file_disk ?: 'local');
        abort_unless($disk->exists($resource->file_path), 404);

        return $disk->download($resource->file_path, $resource->file_name ?: $resource->slug, [
            'Content-Type' => $resource->file_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function editorOrReviewer(Request $request): void
    {
        abort_unless($request->user()->canAny(['manage-knowledge', 'publish-knowledge']), 403);
    }

    private function formData(KnowledgeResource $resource): array
    {
        return [
            'resource' => $resource,
            'categories' => KnowledgeCategory::tree(),
            'mayPublish' => false,
        ];
    }

    private function fill(Request $request, KnowledgeResource $resource): void
    {
        $config = config('knowledge');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('knowledge_resources', 'slug')->ignore($resource->id)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:200000'],
            'resource_type' => ['required', Rule::in(array_keys($config['resource_types']))],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'author' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'max:200'],
            'publisher' => ['nullable', 'string', 'max:200'],
            'publication_date' => ['nullable', 'date'],
            'license' => ['required', Rule::in(array_keys($config['licenses']))],
            'external_url' => ['nullable', 'url:http,https', 'max:500'],
            'file' => ['nullable', 'file', 'max:'.(int) $config['uploads']['max_kb']],
            'remove_file' => ['nullable', 'boolean'],
            'visibility' => ['required', Rule::in(array_keys($config['visibilities']))],
            'audience' => ['nullable', 'array'], 'audience.*' => [Rule::in(array_keys($config['audiences']))],
            'stages' => ['nullable', 'array'], 'stages.*' => [Rule::in(array_keys($config['stages']))],
            'vgi_dimension_keys' => ['nullable', 'array'], 'vgi_dimension_keys.*' => [Rule::in(array_keys($config['vgi_dimensions']))],
            'builder_step_keys' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:500'],
            'review_due_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ], ['slug.regex' => 'Use lower-case letters, numbers and single hyphens only.']);

        $linkedOnly = (bool) ($config['licenses'][$data['license']]['linked_only'] ?? false);
        $willHaveFile = $request->hasFile('file') || ($resource->hasFile() && ! $request->boolean('remove_file'));
        if ($linkedOnly && empty($data['external_url'])) {
            throw ValidationException::withMessages(['external_url' => 'This licence only allows linking: add the link to the original source.']);
        }
        if ($linkedOnly && $willHaveFile) {
            throw ValidationException::withMessages(['file' => 'This licence does not allow Hive to host a copy. Link to the source instead.']);
        }

        $builderKeys = array_values(array_unique(array_filter(array_map(fn ($k) => Str::slug(trim($k), '_'), explode(',', (string) ($data['builder_step_keys'] ?? ''))))));
        $resource->fill([
            ...collect($data)->except(['file', 'remove_file', 'tags', 'change_note', 'builder_step_keys'])->all(),
            'audience' => array_values($data['audience'] ?? []),
            'stages' => array_values($data['stages'] ?? []),
            'vgi_dimension_keys' => array_values($data['vgi_dimension_keys'] ?? []),
            'builder_step_keys' => $builderKeys,
        ]);

        if ($request->boolean('remove_file')) {
            $this->knowledge->removeFile($resource);
        }
        if ($request->hasFile('file')) {
            $this->knowledge->storeFile($resource, $request->file('file'));
        }
    }
}
