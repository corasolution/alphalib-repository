<?php

namespace Alpha\Journal\Enums;

enum ReviewFormElementType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Rating = 'rating';

    /**
     * radio/checkbox/rating need an `options` array on the element; text/textarea don't.
     */
    public function requiresOptions(): bool
    {
        return in_array($this, [self::Radio, self::Checkbox, self::Rating], true);
    }
}
