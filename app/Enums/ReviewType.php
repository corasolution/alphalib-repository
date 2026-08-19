<?php

namespace Alpha\Journal\Enums;

/**
 * §3: anonymous (reviewer hidden), double_anonymous, open. See §4 anonymity boundary — this must
 * be enforced at the API serializer layer, not just in the frontend.
 */
enum ReviewType: string
{
    case Anonymous = 'anonymous';
    case DoubleAnonymous = 'double_anonymous';
    case Open = 'open';
}
