<?php

namespace Alpha\Journal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'review_type' => $this->review_type->value,
            'section_id' => $this->section_id,
            'issue_id' => $this->issue_id,
            'submitted_at' => $this->submitted_at,
            'current_manuscript' => $this->whenLoaded('currentManuscript', fn () => $this->currentManuscript ? [
                'id' => $this->currentManuscript->id,
                'original_name' => $this->currentManuscript->original_name,
                'version' => $this->currentManuscript->version,
                'stage' => $this->currentManuscript->stage->value,
            ] : null),
            'review_rounds' => $this->whenLoaded(
                'reviewRounds',
                fn () => ReviewRoundResource::collection($this->reviewRounds)
            ),
        ];
    }
}
