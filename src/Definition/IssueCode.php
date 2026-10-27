<?php

declare(strict_types=1);

namespace Statecraft\Definition;

enum IssueCode: string
{
    case NoPlaces = 'no_places';
    case DuplicatePlace = 'duplicate_place';
    case MissingInitialPlace = 'missing_initial_place';
    case UnknownInitialPlace = 'unknown_initial_place';
    case MultipleInitialPlaces = 'multiple_initial_places';
    case DuplicateTransition = 'duplicate_transition';
    case EmptyTransition = 'empty_transition';
    case UnknownPlace = 'unknown_place';
    case MultipleTargets = 'multiple_targets';
    case UnreachablePlace = 'unreachable_place';
    case DeadEnd = 'dead_end';
    case FinalPlaceHasExit = 'final_place_has_exit';
}
