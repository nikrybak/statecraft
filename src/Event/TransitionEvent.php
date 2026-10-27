<?php

declare(strict_types=1);

namespace Statecraft\Event;

use Statecraft\Definition\Transition;
use Statecraft\Marking\Marking;
use Statecraft\TransitionContext;

/**
 * Base of all events dispatched through the PSR-14 dispatcher. Listen to a concrete
 * subclass, or to this class when the dispatcher's listener provider resolves parents.
 */
abstract readonly class TransitionEvent
{
    public function __construct(public TransitionContext $context) {}

    public function machine(): string
    {
        return $this->context->definition->name;
    }

    public function subject(): object
    {
        return $this->context->subject;
    }

    public function transition(): Transition
    {
        return $this->context->transition;
    }

    public function markingBefore(): Marking
    {
        return $this->context->marking;
    }
}
