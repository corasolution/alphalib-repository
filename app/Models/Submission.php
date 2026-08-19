<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Submission extends Model
{
    use BelongsToJournal;

    protected $fillable = [
        'journal_id', 'section_id', 'issue_id', 'submitted_by', 'stage', 'review_type',
        'current_publication_id', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => SubmissionStage::class,
            'review_type' => ReviewType::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Publish requires this to be set first (CLAUDE.md §4.1) — enforced in SubmissionWorkflow.
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function currentPublication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'current_publication_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function stageAssignments(): HasMany
    {
        return $this->hasMany(StageAssignment::class);
    }

    public function reviewRounds(): HasMany
    {
        return $this->hasMany(ReviewRound::class);
    }

    public function currentReviewRound(): ?ReviewRound
    {
        return $this->reviewRounds()->latest('round_number')->first();
    }

    public function editorialDecisions(): HasMany
    {
        return $this->hasMany(EditorialDecision::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /**
     * One current manuscript at all times (CLAUDE.md §13.3) — supporting files are a separate
     * flat list (files() minus this one), not a stage bucket.
     */
    public function currentManuscript(): HasOne
    {
        return $this->hasOne(SubmissionFile::class)->where('is_current_manuscript', true);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    /**
     * The audit trail rendered as a UI (CLAUDE.md §13.2 left pane) — every decision, file
     * version, and review event logged against this submission via Activity::log().
     */
    public function activity(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
