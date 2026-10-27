<?php

declare(strict_types=1);

namespace Statecraft\Examples\OrderLifecycle;

use Statecraft\Guard\Guard;
use Statecraft\Guard\GuardResult;
use Statecraft\TransitionContext;

final class PaymentCaptured implements Guard
{
    public function check(TransitionContext $context): GuardResult
    {
        $paymentId = $context->get('payment_id');

        return GuardResult::when(\is_string($paymentId) && $paymentId !== '', 'a captured payment id is required');
    }
}
