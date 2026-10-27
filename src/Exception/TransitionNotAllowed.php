<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use RuntimeException;
use Statecraft\TransitionBlocker;

final class TransitionNotAllowed extends RuntimeException implements StatecraftException
{
    /**
     * @param non-empty-list<TransitionBlocker> $blockers
     */
    public function __construct(
        public readonly string $machine,
        public readonly string $transition,
        public readonly array $blockers,
    ) {
        $reasons = array_map(static fn(TransitionBlocker $b): string => $b->message, $blockers);

        parent::__construct(\sprintf(
            'Transition "%s" of machine "%s" is not allowed: %s',
            $transition,
            $machine,
            implode('; ', $reasons),
        ));
    }
}
