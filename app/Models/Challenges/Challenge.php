<?php

namespace App\Models\Challenges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A Challenge Exchange entry. The columns hold the working copy (what editors are changing);
 * the public only ever sees the immutable snapshot referenced by published_version_id.
 */
class Challenge extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'changes_requested' => 'Changes requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    /** States in which the working copy may be edited. Editing a published or approved challenge starts a new draft revision. */
    public const EDITABLE = ['draft', 'changes_requested', 'rejected', 'approved', 'published'];

    public const VISIBILITIES = ['public' => 'Everyone', 'members' => 'Signed-in members only'];

    /** Content fields captured in every version snapshot. */
    public const CONTENT_FIELDS = ['title', 'problem_description', 'sector', 'geography', 'country', 'region', 'market_opportunity',
        'existing_solutions', 'policy_considerations', 'research_references', 'priority', 'category', 'tags', 'issuer_name',
        'issuer_type', 'organization_id', 'visibility', 'vgi_dimension_keys', 'builder_step_keys', 'expert_categories'];

    protected $fillable = self::CONTENT_FIELDS;

    protected function casts(): array
    {
        return [
            'research_references' => 'array', 'tags' => 'array', 'vgi_dimension_keys' => 'array', 'builder_step_keys' => 'array',
            'expert_categories' => 'array', 'organization_id' => 'integer', 'current_version' => 'integer',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime',
            'archived_at' => 'datetime', 'last_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Challenge $challenge) {
            $challenge->uuid ??= (string) Str::uuid();
            $challenge->slug ??= self::uniqueSlug($challenge->title);
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'challenge';
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ChallengeVersion::class)->orderByDesc('version');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ChallengeVersion::class, 'published_version_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ChallengeEvidence::class)->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ChallengeReview::class)->latest('id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(ChallengeBookmark::class);
    }

    public function solutionSubmissions(): HasMany
    {
        return $this->hasMany(ChallengeSolutionSubmission::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /** Live on the public site: a published snapshot exists and the challenge is not archived. */
    public function isLive(): bool
    {
        return $this->published_version_id !== null && $this->archived_at === null && $this->status !== 'archived';
    }

    /** Visible to this viewer on the public site (published snapshot + visibility rule). */
    public function isVisibleTo(?User $user): bool
    {
        if (! $this->isLive() || ! $this->publishedVersion) {
            return false;
        }

        return $this->publishedVersion->visibility === 'public' || $user !== null;
    }

    /** The content snapshot of the working copy (what a new version would contain). */
    public function contentSnapshot(): array
    {
        $content = [];
        foreach (self::CONTENT_FIELDS as $field) {
            $content[$field] = $this->getAttribute($field);
        }
        $content['evidence_ids'] = $this->evidence()->pluck('id')->all();

        return $content;
    }
}
