<?php

namespace Alpha\Journal\Http\Resources;

use Alpha\Journal\Reviewers\ReviewerIdentityVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anonymity is a security boundary (CLAUDE.md §4): reviewer identity is stripped here, at the
 * serializer layer, never merely hidden in the frontend. The actual rule lives in
 * ReviewerIdentityVisibility — every review type except `open` hides the reviewer from everyone
 * but the journal's editors; do not reintroduce `reviewer_id`/`reviewer` anywhere else in a
 * submission-facing payload without going through that class.
 */
class ReviewAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeIdentity = ReviewerIdentityVisibility::canSee($request->user(), $this->resource);

        return [
            'id' => $this->id,
            'review_round_id' => $this->review_round_id,
            'reviewer' => $canSeeIdentity ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'recommendation' => $this->completed_at !== null ? $this->recommendation?->value : null,
            'invited_at' => $this->invited_at,
            'due_at' => $this->due_at,
            'responded_at' => $this->responded_at,
            'declined_at' => $this->declined_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
