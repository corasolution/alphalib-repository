<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\AiFeature;
use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One logged AI call (CLAUDE.md §6 guardrail 3). Journal-scoped like everything else, so an
 * auditor on one journal can never read another journal's AI history.
 *
 * Deliberately NOT immutable, unlike EditorialDecision: the accept/reject columns are written
 * after the fact, when a human keeps or discards what the model proposed.
 */
class AiRun extends Model
{
    use BelongsToJournal;

    protected $fillable = [
        'journal_id', 'submission_id', 'feature', 'model', 'prompt_version', 'status',
        'input_tokens', 'output_tokens', 'cost_microcents', 'latency_ms',
        'output', 'error', 'accepted_at', 'rejected_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'status' => AiRunStatus::class,
            'output' => 'array',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Closes the improvement loop. "Accepted" means a human kept what the model proposed; the
     * distinction from an unjudged run is what makes this table worth querying later, so both
     * timestamps are cleared before setting one — a run judged twice must not read as both.
     */
    public function markReviewed(bool $accepted, ?User $by = null): void
    {
        $this->update([
            'accepted_at' => $accepted ? now() : null,
            'rejected_at' => $accepted ? null : now(),
            'reviewed_by' => $by?->id,
        ]);
    }

    /** Cost in whole cents, for display. Haiku-scale runs are routinely 0. */
    public function costInCents(): float
    {
        return round($this->cost_microcents / 10000, 4);
    }
}
