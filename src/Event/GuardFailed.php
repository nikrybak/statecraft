<?php

declare(strict_types=1);

namespace Statecraft\Event;

use Statecraft\Guard\GuardResult;
use Statecraft\TransitionContext;

/**
 * A guard rejected a transition during apply(). Not dispatched for can() and
 * enabledTransitions(), which are queries and stay free of side effects.
 */
final readonly class GuardFailed extends TransitionEvent
{
    public function __construct(
        TransitionContext $context,
        public GuardResult $result,
    ) {
        parent::__construct($context);
    }

    public function reason(): string
    {
        return $this->result->reason;
    }
}
