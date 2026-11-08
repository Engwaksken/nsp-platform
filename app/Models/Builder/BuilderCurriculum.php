<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** One version of the Venture Builder curriculum: draft, then published (frozen), then retired. */
class BuilderCurriculum extends Model
{
    protected $table = 'builder_curricula';

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];

    public const METHODOLOGY = ['provisional' => 'Provisional (awaiting owner approval)', 'owner_approved' => 'Owner approved'];

    protected $fillable = ['name', 'version', 'status', 'methodology_status', 'description', 'note', 'copied_from_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'version' => 'integer'];
    }

    /** The published version new enrolments use (the most recently published). */
    public static function current(): ?self
    {
        return self::query()->where('status', 'published')->latest('published_at')->latest('id')->first();
    }

    public function isProvisional(): bool
    {
        return $this->methodology_status !== 'owner_approved';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function modules(): HasMany
    {
        return $this->hasMany(BuilderModule::class, 'curriculum_id')->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(BuilderStepDefinition::class, 'curriculum_id');
    }

    /**
     * Active steps of active modules in sequence order (module position, then step position),
     * each with its module relation set.
     *
     * @return Collection<int, BuilderStepDefinition>
     */
    public function orderedSteps(): Collection
    {
        if (! $this->relationLoaded('modules') || ($this->modules->isNotEmpty() && ! $this->modules->first()->relationLoaded('steps'))) {
            $this->load('modules.steps');
        }

        return $this->modules->where('is_active', true)->sortBy('position')
            ->flatMap(fn (BuilderModule $module) => $module->steps->where('is_active', true)->sortBy('position')
                ->each(fn (BuilderStepDefinition $step) => $step->setRelation('module', $module)))
            ->values();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
