<?php

declare(strict_types=1);

namespace Statecraft\Guard;

use Closure;
use Statecraft\Exception\GuardResolutionFailed;
use Statecraft\TransitionContext;

/**
 * Adapts a closure returning bool or GuardResult to the Guard interface.
 */
final readonly class ClosureGuard implements Guard
{
    public function __construct(private Closure $closure) {}

    public function check(TransitionContext $context): GuardResult
    {
        $result = ($this->closure)($context);

        return match (true) {
            $result instanceof GuardResult => $result,
            \is_bool($result) => GuardResult::when($result, 'condition returned false'),
            default => throw new GuardResolutionFailed(\sprintf(
                'Guard closure of transition "%s" must return bool or %s, got %s.',
                $context->transition->name,
                GuardResult::class,
                get_debug_type($result),
            )),
        };
    }
}
