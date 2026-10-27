<?php

declare(strict_types=1);

namespace Statecraft\Guard;

final readonly class GuardResult
{
    private function __construct(
        public bool $allowed,
        public string $reason,
    ) {}

    public static function allow(): self
    {
        return new self(true, '');
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }

    public static function when(bool $condition, string $reasonIfDenied): self
    {
        return $condition ? self::allow() : self::deny($reasonIfDenied);
    }
}
