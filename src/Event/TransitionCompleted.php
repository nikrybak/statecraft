<?php

declare(strict_types=1);

namespace Statecraft\Event;

use Statecraft\Marking\Marking;
use Statecraft\TransitionContext;

/**
 * The new marking has been written to the subject and recorded in the audit trail.
 */
final readonly class TransitionCompleted extends TransitionEvent
{
    public function __construct(
        TransitionContext $context,
        public Marking $markingAfter,
    ) {
        parent::__construct($context);
    }
}
