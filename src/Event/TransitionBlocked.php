<?php

declare(strict_types=1);

namespace Statecraft\Event;

use Statecraft\TransitionBlocker;
use Statecraft\TransitionContext;

/**
 * apply() was called for a transition that cannot fire; it is dispatched right before the exception.
 */
final readonly class TransitionBlocked extends TransitionEvent
{
    /**
     * @param non-empty-list<TransitionBlocker> $blockers
     */
    public function __construct(
        TransitionContext $context,
        public array $blockers,
    ) {
        parent::__construct($context);
    }
}
