<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use OutOfBoundsException;

final class UndefinedTransition extends OutOfBoundsException implements StatecraftException
{
    public function __construct(
        public readonly string $machine,
        public readonly string $transition,
    ) {
        parent::__construct(\sprintf('Machine "%s" has no transition named "%s".', $machine, $transition));
    }
}
