<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;

#[StateMachine(name: 'order', metadata: ['owner' => 'sales'])]
#[Transition('cancel', from: [self::New, self::Paid], to: self::Cancelled)]
enum OrderStatus: string
{
    #[State(initial: true, label: 'New order')]
    #[Transition('pay', to: self::Paid, guard: PositiveAmount::class)]
    case New = 'new';

    #[Transition('ship', to: self::Shipped)]
    case Paid = 'paid';

    #[Transition('deliver', to: self::Delivered)]
    case Shipped = 'shipped';

    #[State(final: true)]
    case Delivered = 'delivered';

    #[State(final: true)]
    case Cancelled = 'cancelled';
}
