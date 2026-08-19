<?php

namespace Alpha\Journal\Workflow\Exceptions;

use RuntimeException;

/**
 * The actor lacks approval authority for this decision (CLAUDE.md §4.1).
 */
class WorkflowAuthorizationException extends RuntimeException {}
