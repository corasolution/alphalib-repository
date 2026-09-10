<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Every decision stores actor, role used, timestamp, target state, reason text, and the file
 * versions in scope at that moment (CLAUDE.md §4.1). Immutable except for the one legitimate
 * mutation — a second editor's countersign() under require_dual_approval. newEloquentBuilder()
 * enforces this at the one place both $model->save()/delete() and bulk
 * EditorialDecision::where(...)->update()/delete() actually funnel through — see
 * ImmutableBuilder. $updateGateOpen is the only way through, and only countersign() ever opens it.
 */
class EditorialDecision extends Model
{
    const UPDATED_AT = null;

    protected $table = 'editorial_decisions';

    private bool $updateGateOpen = false;

    protected $fillable = [
        'submission_id', 'review_round_id', 'actor_id', 'role_used', 'decision',
        'target_stage', 'reason', 'requires_countersign', 'countersigned_by',
        'countersigned_at', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'decision' => EditorialDecisionType::class,
            'target_stage' => SubmissionStage::class,
            'role_used' => JournalRole::class,
            'requires_countersign' => 'boolean',
            'countersigned_at' => 'datetime',
            'properties' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewRound(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function countersignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'countersigned_by');
    }

    public function isAwaitingCountersign(): bool
    {
        return $this->requires_countersign && $this->countersigned_by === null;
    }

    public function countersign(User $actor): void
    {
        if (! $this->requires_countersign) {
            throw new LogicException('This decision does not require a countersign.');
        }

        if ($this->countersigned_by !== null) {
            throw new LogicException('This decision has already been countersigned.');
        }

        if ($actor->id === $this->actor_id) {
            throw new LogicException('The original decision-maker cannot countersign their own decision.');
        }

        $this->updateGateOpen = true;

        try {
            $this->forceFill([
                'countersigned_by' => $actor->id,
                'countersigned_at' => now(),
            ])->save();
        } finally {
            $this->updateGateOpen = false;
        }
    }

    /**
     * Read by ImmutableBuilder — true only for the instant countersign() is writing.
     */
    public function allowsThisUpdate(): bool
    {
        return $this->updateGateOpen;
    }

    /**
     * ImmutableBuilder's update/delete guards, plus EditorialDecisionBuilder's insert guard —
     * decisions are immutable once made, and an AI code path may not make one at all.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new EditorialDecisionBuilder($query);
    }
}
