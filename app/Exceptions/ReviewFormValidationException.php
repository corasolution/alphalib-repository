<?php

namespace Alpha\Journal\Exceptions;

use Alpha\Journal\Models\ReviewFormElement;
use Illuminate\Support\Collection;
use RuntimeException;

class ReviewFormValidationException extends RuntimeException
{
    /** @param Collection<int, ReviewFormElement> $missingElements */
    public function __construct(public readonly Collection $missingElements)
    {
        $labels = $missingElements->map(fn (ReviewFormElement $e) => $e->label->get('en') ?? "#{$e->id}")->implode(', ');

        parent::__construct("Missing required review form answers: {$labels}");
    }
}
