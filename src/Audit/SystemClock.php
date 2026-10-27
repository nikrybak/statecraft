<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    public function __construct(private DateTimeZone $timezone = new DateTimeZone('UTC')) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
