<?php

namespace Alpha\Journal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewRoundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'status' => $this->status,
            'review_assignments' => $this->whenLoaded(
                'reviewAssignments',
                fn () => ReviewAssignmentResource::collection($this->reviewAssignments)
            ),
        ];
    }
}
