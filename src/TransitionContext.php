<?php

declare(strict_types=1);

namespace Statecraft;

use Statecraft\Definition\Definition;
use Statecraft\Definition\Transition;
use Statecraft\Marking\Marking;

/**
 * Everything a guard, hook or listener may need to know about a transition attempt.
 */
final readonly class TransitionContext
{
    /**
     * @param Marking              $marking  the marking before the transition
     * @param array<string, mixed> $metadata caller-supplied data, also stored in the audit trail
     */
    public function __construct(
        public Definition $definition,
        public object $subject,
        public Transition $transition,
        public Marking $marking,
        public ?string $actor = null,
        public array $metadata = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->metadata) ? $this->metadata[$key] : $default;
    }
}
