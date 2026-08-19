<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\ReviewRecommendation;
use Alpha\Journal\Exceptions\ReviewFormValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ReviewAssignment extends Model
{
    protected $fillable = [
        'review_round_id', 'reviewer_id', 'recommendation',
        'invited_at', 'due_at', 'responded_at', 'declined_at', 'completed_at',
        'last_reminder_sent_at', 'editor_escalated_at', 'quality_rating',
    ];

    protected function casts(): array
    {
        return [
            'recommendation' => ReviewRecommendation::class,
            'invited_at' => 'datetime',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'declined_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'editor_escalated_at' => 'datetime',
        ];
    }

    public function reviewRound(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class);
    }

    /**
     * Never expose this relation directly through an API resource for double-anonymous review —
     * it's stripped at the serializer layer by ReviewAssignmentResource (CLAUDE.md §4). Load it
     * freely on the backend; just don't hand a hydrated ReviewAssignment straight to a resource
     * that isn't ReviewAssignmentResource.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ReviewFormResponse::class);
    }

    /**
     * @param  array<int, mixed>  $answersByElementId  review_form_element_id => answer
     *
     * @throws ReviewFormValidationException if the section's review form has unanswered required elements
     */
    public function submitReview(array $answersByElementId, ReviewRecommendation $recommendation): void
    {
        $reviewForm = $this->reviewRound->submission->section?->reviewForm;

        if ($reviewForm) {
            $missing = $reviewForm->missingRequiredElements($answersByElementId);

            if ($missing->isNotEmpty()) {
                throw new ReviewFormValidationException($missing);
            }

            foreach ($answersByElementId as $elementId => $value) {
                $this->responses()->updateOrCreate(
                    ['review_form_element_id' => $elementId],
                    ['value' => $value]
                );
            }
        }

        $this->update([
            'recommendation' => $recommendation->value,
            'completed_at' => now(),
        ]);
    }

    /**
     * Private editor rating of review quality, 1-5 (CLAUDE.md §11.4). Never expose this to the
     * reviewer being rated — it isn't included in ReviewAssignmentResource.
     */
    public function rateQuality(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException("Quality rating must be between 1 and 5, got {$rating}.");
        }

        if ($this->completed_at === null) {
            throw new InvalidArgumentException('Cannot rate a review that has not been completed.');
        }

        $this->update(['quality_rating' => $rating]);
    }

    /**
     * Accept or decline in one tap from the invitation email via a signed, expiring link — no
     * login required (CLAUDE.md §13.7, called out as the single highest-impact reviewer UX
     * change in the whole spec). ReviewInvitationController validates the signature; these
     * methods only guard against responding twice or after the review is already done.
     */
    public function acceptInvitation(): void
    {
        $this->guardCanRespond();

        $this->update(['responded_at' => now()]);
    }

    public function declineInvitation(): void
    {
        $this->guardCanRespond();

        $this->update(['responded_at' => now(), 'declined_at' => now()]);
    }

    private function guardCanRespond(): void
    {
        if ($this->completed_at !== null) {
            throw new InvalidArgumentException('This review has already been completed.');
        }

        if ($this->responded_at !== null) {
            throw new InvalidArgumentException('This invitation has already been responded to.');
        }
    }
}
