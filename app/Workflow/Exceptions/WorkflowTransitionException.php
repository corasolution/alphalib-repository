<?php

namespace Alpha\Journal\Workflow\Exceptions;

use RuntimeException;

/**
 * The decision is illegal from the submission's current stage, or the stage has no assigned
 * editor (CLAUDE.md §3: "A submission can only advance if the current stage has at least one
 * assigned editor. Enforce in the state machine, not the UI.").
 */
class WorkflowTransitionException extends RuntimeException {}
