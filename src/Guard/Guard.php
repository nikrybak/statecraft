<?php

declare(strict_types=1);

namespace Statecraft\Guard;

use Statecraft\TransitionContext;

/**
 * A reusable condition that must hold for a transition to fire.
 *
 * Implementations should be free of side effects: guards also run for can() and
 * enabledTransitions(), which callers expect to be safe to call any number of times.
 */
interface Guard
{
    public function check(TransitionContext $context): GuardResult;
}
