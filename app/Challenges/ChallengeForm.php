<?php

namespace App\Challenges;

use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupProfile;
use App\Models\User;
use App\Support\Audit;
use App\Support\PageBlocks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Validation shared by the member submission form and the admin editor. */
class ChallengeForm
{
    public static function validated(Request $request, bool $staff): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'problem_description' => ['required', 'string', 'max:10000'],
            'sector' => ['nullable', Rule::in(StartupProfile::SECTORS)],
            'geography' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', Rule::in(array_keys(Startup::COUNTRIES))],
            'region' => ['nullable', 'string', 'max:120'],
            'market_opportunity' => ['nullable', 'string', 'max:5000'],
            'existing_solutions' => ['nullable', 'string', 'max:5000'],
            'policy_considerations' => ['nullable', 'string', 'max:5000'],
            'references' => ['nullable', 'array', 'max:20'],
            'references.*.title' => ['nullable', 'string', 'max:200'],
            'references.*.url' => ['nullable', 'url:https,http', 'max:500'],
            'references.*.accessed' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(array_keys(config('challenges.priorities')))],
            'category' => ['required', Rule::in(array_keys(config('challenges.categories')))],
            'tags' => ['nullable', 'string', 'max:300'],
            'issuer_name' => ['nullable', 'string', 'max:160'],
            'issuer_type' => ['nullable', Rule::in(array_keys(config('challenges.issuer_types')))],
            'visibility' => ['required', Rule::in(array_keys(Challenge::VISIBILITIES))],
            'vgi_dimension_keys' => ['nullable', 'string', 'max:500'],
            'builder_step_keys' => ['nullable', 'string', 'max:500'],
            'expert_categories' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
        ], ['references.*.url.url' => 'Enter the full address, starting with https://']);

        $references = [];
        foreach ($data['references'] ?? [] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $url = PageBlocks::safeUrl((string) ($row['url'] ?? ''));
            if ($title === '' && $url === '') {
                continue;
            }
            $references[] = ['title' => $title !== '' ? $title : $url, 'url' => $url ?: null, 'accessed' => $row['accessed'] ?? null];
        }

        $clean = array_intersect_key($data, array_flip(['title', 'problem_description', 'sector', 'geography', 'country', 'region',
            'market_opportunity', 'existing_solutions', 'policy_considerations', 'priority', 'category', 'issuer_name', 'issuer_type', 'visibility']));
        $clean += array_fill_keys(['sector', 'geography', 'country', 'region', 'market_opportunity', 'existing_solutions', 'policy_considerations', 'issuer_name', 'issuer_type'], null);
        $clean['research_references'] = $references ?: null;
        $clean['tags'] = self::list($data['tags'] ?? '', 20, false) ?: null;
        foreach (['vgi_dimension_keys', 'builder_step_keys', 'expert_categories'] as $field) {
            $clean[$field] = self::list($data[$field] ?? '', 20, true) ?: null;
        }
        if ($staff) {
            $clean['organization_id'] = $data['organization_id'] ?? null;
        }

        return $clean;
    }

    /** Comma-separated text to a de-duplicated list; keys are reduced to [a-z0-9_.-]. */
    public static function list(?string $text, int $max, bool $keys): array
    {
        $items = array_map('trim', explode(',', (string) $text));
        if ($keys) {
            $items = array_map(fn ($i) => preg_replace('/[^a-z0-9_.\-]/', '', strtolower(str_replace(' ', '_', $i))), $items);
        } else {
            $items = array_map(fn ($i) => mb_substr($i, 0, 40), $items);
        }

        return array_slice(array_values(array_unique(array_filter($items, fn ($i) => $i !== ''))), 0, $max);
    }

    /** Add a link or private file as evidence. Files go to the private disk and are validated by content type. */
    public static function addEvidence(Request $request, Challenge $challenge): ChallengeEvidence
    {
        $data = $request->validate([
            'source_title' => ['required', 'string', 'max:200'],
            'evidence_type' => ['required', Rule::in(array_keys(config('challenges.evidence_types')))],
            'url' => ['nullable', 'url:https,http', 'max:500', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:'.config('challenges.evidence_max_kb'), 'extensions:'.config('challenges.evidence_extensions'),
                'mimetypes:'.implode(',', config('challenges.evidence_mime_types'))],
        ], ['url.required_without' => 'Add a link or upload a document.', 'url.url' => 'Enter the full address, starting with https://']);

        $attributes = [
            'challenge_id' => $challenge->id, 'source_title' => $data['source_title'], 'evidence_type' => $data['evidence_type'],
            'url' => PageBlocks::safeUrl((string) ($data['url'] ?? '')) ?: null, 'added_by' => $request->user()->id,
        ];
        if ($file = $request->file('file')) {
            $attributes += [
                'disk' => 'local', 'path' => $file->store('challenge-evidence/'.$challenge->uuid, 'local'),
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                'mime_type' => (string) $file->getMimeType(), 'size' => $file->getSize(),
            ];
        }
        $evidence = ChallengeEvidence::query()->create($attributes);
        Audit::log('challenge.evidence_added', $challenge, ['evidence_id' => $evidence->id]);

        return $evidence;
    }

    /** Remove evidence that is not part of the published version (published sources change only through review). */
    public static function removeEvidence(Challenge $challenge, ChallengeEvidence $evidence): void
    {
        abort_unless($evidence->challenge_id === $challenge->id, 404);
        $published = (array) ($challenge->publishedVersion?->field('evidence_ids') ?? []);
        if (in_array($evidence->id, $published, true)) {
            throw ValidationException::withMessages(['evidence' => 'This source is part of the published version and cannot be removed. Archive or revise the challenge instead.']);
        }
        if ($evidence->path) {
            Storage::disk($evidence->disk ?: 'local')->delete($evidence->path);
        }
        Audit::log('challenge.evidence_removed', $challenge, ['evidence_id' => $evidence->id]);
        $evidence->delete();
    }

    /** Who may download private evidence files: the submitter and challenge staff. Never the public. */
    public static function canSeePrivateEvidence(?User $user, Challenge $challenge): bool
    {
        return $user !== null && ($user->id === $challenge->submitted_by || $user->canAny(['manage-challenges', 'review-challenges']));
    }
}
