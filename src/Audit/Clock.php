<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use DateTimeImmutable;

/**
 * Same shape as PSR-20 ClockInterface, so a PSR-20 clock can be wrapped in one line,
 * without adding psr/clock as a hard dependency of the core.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
