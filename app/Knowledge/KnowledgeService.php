<?php

namespace App\Knowledge;

use App\Models\Knowledge\KnowledgeBookmark;
use App\Models\Knowledge\KnowledgeDownload;
use App\Models\Knowledge\KnowledgeIndexMetadata;
use App\Models\Knowledge\KnowledgeRecentView;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Knowledge\KnowledgeResourceVersion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Editorial workflow, file storage, versions, bookmarks, downloads and views for the Knowledge Hub. */
class KnowledgeService
{
    /** Validates an upload by its contents (not its name) and stores it on the private disk. */
    public function storeFile(KnowledgeResource $resource, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('knowledge.uploads.allowed', []);
        $mime = (string) $file->getMimeType(); // detected from the file's bytes
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages(['file' => 'The file contents do not match an allowed type ('.strtoupper(implode(', ', array_keys($allowed))).').']);
        }
        if ($file->getSize() > (int) config('knowledge.uploads.max_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'The file is larger than the '.(int) config('knowledge.uploads.max_kb').' KB limit.']);
        }

        $disk = (string) config('knowledge.uploads.disk', 'local');
        // Random name: never trust the uploaded name on disk. Old files stay for earlier versions.
        $path = $file->storeAs('knowledge/'.$resource->uuid, Str::uuid().'.'.$extension, $disk);
        $resource->forceFill([
            'file_disk' => $disk,
            'file_path' => $path,
            'file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'file_mime' => $mime,
            'file_size' => $file->getSize(),
        ]);
    }

    public function removeFile(KnowledgeResource $resource): void
    {
        $resource->forceFill(['file_disk' => null, 'file_path' => null, 'file_name' => null, 'file_mime' => null, 'file_size' => null]);
    }

    public function submit(KnowledgeResource $resource, User $by): void
    {
        abort_unless($resource->status === 'draft', 422, 'Only drafts can be submitted for review.');
        $resource->forceFill(['status' => 'in_review', 'submitted_at' => now()])->save();
        Audit::log('knowledge.submitted', $resource, ['title' => $resource->title]);
    }

    /** Sends a resource back to its editors with a note. */
    public function returnToDraft(KnowledgeResource $resource, User $by, ?string $note): void
    {
        $resource->forceFill(['status' => 'draft', 'review_note' => $note, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        Audit::log('knowledge.returned', $resource, ['title' => $resource->title]);
    }

    /** Whether $by may publish $resource under the four-eyes rule. */
    public function mayPublish(KnowledgeResource $resource, User $by): bool
    {
        if (! $by->can('publish-knowledge')) {
            return false;
        }
        if (config('knowledge.workflow.four_eyes') && $resource->uploaded_by === $by->id && ! $by->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function publish(KnowledgeResource $resource, User $by, ?string $note = null): KnowledgeResourceVersion
    {
        abort_unless(in_array($resource->status, ['in_review', 'archived'], true), 422, 'Submit the resource for review before publishing.');
        abort_unless($this->mayPublish($resource, $by), 403, 'Another editor must review and publish a resource you uploaded.');

        return DB::transaction(function () use ($resource, $by, $note) {
            $resource->forceFill([
                'status' => 'published',
                'published_at' => $resource->published_at && $resource->published_at->isFuture() ? $resource->published_at : now(),
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'review_due_at' => $resource->review_due_at ?? now()->addMonths((int) config('knowledge.workflow.review_months', 12)),
            ])->save();
            $version = $this->snapshot($resource, $by, $note ?: 'Published');
            Audit::log('knowledge.published', $resource, ['title' => $resource->title, 'version' => $version->version]);

            return $version;
        });
    }

    public function archive(KnowledgeResource $resource, User $by): void
    {
        $resource->forceFill(['status' => 'archived'])->save();
        KnowledgeIndexMetadata::query()->where('knowledge_resource_id', $resource->id)->update(['indexed_at' => null]);
        Audit::log('knowledge.archived', $resource, ['title' => $resource->title]);
    }

    /** Records an immutable copy of what was published and flags the resource for (re)indexing. */
    public function snapshot(KnowledgeResource $resource, User $by, ?string $note): KnowledgeResourceVersion
    {
        $resource->loadMissing('tags', 'category');
        $number = (int) $resource->versions()->max('version') + 1;
        $snapshot = [
            'title' => $resource->title, 'slug' => $resource->slug, 'summary' => $resource->summary, 'body' => $resource->body,
            'resource_type' => $resource->resource_type, 'category' => $resource->category?->name,
            'author' => $resource->author, 'source' => $resource->source, 'publisher' => $resource->publisher,
            'publication_date' => $resource->publication_date?->toDateString(), 'license' => $resource->license,
            'external_url' => $resource->external_url, 'visibility' => $resource->visibility,
            'audience' => $resource->audience, 'stages' => $resource->stages, 'vgi_dimension_keys' => $resource->vgi_dimension_keys,
            'builder_step_keys' => $resource->builder_step_keys, 'tags' => $resource->tags->pluck('name')->all(),
            'expires_at' => $resource->expires_at?->toIso8601String(), 'review_due_at' => $resource->review_due_at?->toIso8601String(),
        ];
        $version = KnowledgeResourceVersion::query()->create([
            'knowledge_resource_id' => $resource->id, 'version' => $number, 'snapshot' => $snapshot,
            'file_path' => $resource->file_path, 'file_name' => $resource->file_name, 'file_mime' => $resource->file_mime,
            'file_size' => $resource->file_size, 'change_note' => $note ? mb_substr($note, 0, 500) : null, 'published_by' => $by->id,
        ]);
        $resource->forceFill(['current_version' => $number])->save();

        KnowledgeIndexMetadata::query()->updateOrCreate(
            ['knowledge_resource_id' => $resource->id, 'index_name' => (string) config('knowledge.semantic.index_name', 'knowledge-v1')],
            ['visibility' => $resource->visibility, 'version' => $number, 'checksum' => hash('sha256', json_encode($snapshot)), 'indexed_at' => null],
        );

        return $version;
    }

    public function toggleBookmark(User $user, KnowledgeResource $resource): bool
    {
        $existing = KnowledgeBookmark::query()->where('user_id', $user->id)->where('knowledge_resource_id', $resource->id)->first();
        if ($existing) {
            $existing->delete();

            return false;
        }
        KnowledgeBookmark::query()->create(['user_id' => $user->id, 'knowledge_resource_id' => $resource->id]);

        return true;
    }

    public function recordDownload(?User $user, KnowledgeResource $resource, string $kind, ?string $ip): void
    {
        KnowledgeDownload::query()->create([
            'user_id' => $user?->id, 'knowledge_resource_id' => $resource->id, 'version' => $resource->current_version ?: null,
            'kind' => $kind, 'ip_hash' => Audit::hashIdentifier($ip),
        ]);
        KnowledgeResource::query()->whereKey($resource->id)->increment('download_count');
    }

    /** Keeps the user's most recent views (one row per resource), trimmed to the configured length. */
    public function recordView(User $user, KnowledgeResource $resource): void
    {
        KnowledgeRecentView::query()->updateOrCreate(
            ['user_id' => $user->id, 'knowledge_resource_id' => $resource->id],
            ['viewed_at' => now()],
        );
        $keep = KnowledgeRecentView::query()->where('user_id', $user->id)->orderByDesc('viewed_at')->orderByDesc('id')
            ->limit((int) config('knowledge.recent_views_kept', 20))->pluck('id');
        KnowledgeRecentView::query()->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }
}
