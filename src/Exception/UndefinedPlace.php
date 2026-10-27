<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use OutOfBoundsException;

final class UndefinedPlace extends OutOfBoundsException implements StatecraftException
{
    public function __construct(
        public readonly string $machine,
        public readonly string $place,
    ) {
        parent::__construct(\sprintf('Machine "%s" has no state named "%s".', $machine, $place));
    }
}
