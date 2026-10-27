<?php

declare(strict_types=1);

namespace Statecraft;

/**
 * One reason why a transition cannot be applied right now.
 */
final readonly class TransitionBlocker
{
    public const string NOT_ENABLED = 'not_enabled';
    public const string GUARD_FAILED = 'guard_failed';

    public function __construct(
        public string $code,
        public string $message,
    ) {}

    public static function notEnabled(string $transition, string $marking): self
    {
        return new self(self::NOT_ENABLED, \sprintf('"%s" cannot fire from the current marking [%s]', $transition, $marking));
    }

    public static function guardFailed(string $transition, string $reason): self
    {
        return new self(self::GUARD_FAILED, \sprintf('guard of "%s" rejected it: %s', $transition, $reason));
    }
}
