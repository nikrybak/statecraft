<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures\Broken;

use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;

#[StateMachine(name: 'broken')]
enum BrokenMachine: string
{
    #[State(initial: true)]
    #[Transition('go', to: self::Stuck)]
    case Start = 'start';

    case Stuck = 'stuck';

    case Orphan = 'orphan';
}
