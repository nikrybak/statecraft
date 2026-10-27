<?php

declare(strict_types=1);

namespace Statecraft\Definition;

use Closure;
use Statecraft\Guard\Guard;
use Statecraft\Guard\GuardResult;
use Statecraft\TransitionContext;

final readonly class Transition
{
    /**
     * @param list<string>                                   $froms
     * @param list<string>                                   $tos
     * @param Guard|(Closure(TransitionContext): (bool|GuardResult))|string|null $guard a Guard, a closure, or a class name / container id of a Guard
     * @param array<string, mixed>                           $metadata
     */
    public function __construct(
        public string $name,
        public array $froms,
        public array $tos,
        public Guard|Closure|string|null $guard = null,
        public array $metadata = [],
    ) {}

    public function hasGuard(): bool
    {
        return $this->guard !== null;
    }
}
