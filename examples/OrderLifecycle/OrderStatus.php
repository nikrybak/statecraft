<?php

declare(strict_types=1);

namespace Statecraft\Examples\OrderLifecycle;

use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;

#[StateMachine(name: 'order')]
#[Transition('cancel', from: [self::Pending, self::Paid], to: self::Cancelled)]
enum OrderStatus: string
{
    #[State(initial: true)]
    #[Transition('pay', to: self::Paid, guard: PaymentCaptured::class)]
    case Pending = 'pending';

    #[Transition('ship', to: self::Shipped)]
    case Paid = 'paid';

    #[Transition('deliver', to: self::Delivered)]
    #[Transition('return', to: self::Returned)]
    case Shipped = 'shipped';

    #[State(final: true)]
    case Delivered = 'delivered';

    #[State(final: true)]
    case Returned = 'returned';

    #[State(final: true)]
    case Cancelled = 'cancelled';
}
