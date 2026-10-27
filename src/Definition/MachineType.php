<?php

declare(strict_types=1);

namespace Statecraft\Definition;

enum MachineType: string
{
    /**
     * Exactly one state is marked at any time. A transition with several "from" states
     * fires from any one of them and has exactly one target.
     */
    case StateMachine = 'state_machine';

    /**
     * Several places can be marked at once (a 1-safe Petri net). A transition fires only when
     * every "from" place is marked, consumes all of them and marks every "to" place.
     */
    case Workflow = 'workflow';
}
