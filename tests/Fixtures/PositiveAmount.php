<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use Statecraft\Guard\Guard;
use Statecraft\Guard\GuardResult;
use Statecraft\TransitionContext;

final class PositiveAmount implements Guard
{
    public function check(TransitionContext $context): GuardResult
    {
        $order = $context->subject;

        return GuardResult::when($order instanceof Order && $order->amount > 0, 'order amount must be positive');
    }
}
