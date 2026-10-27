<?php

declare(strict_types=1);

namespace Statecraft\Attribute;

use Attribute;
use UnitEnum;

/**
 * Declares a transition, either on the machine class (with explicit "from") or on a
 * state constant / enum case, where "from" defaults to that state.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class Transition
{
    /**
     * @param string|UnitEnum|list<string|UnitEnum>|null $from
     * @param string|UnitEnum|list<string|UnitEnum>      $to
     * @param string|null                                $guard class name of a Guard or a container service id
     * @param array<string, mixed>                       $metadata
     */
    public function __construct(
        public string $name,
        public string|UnitEnum|array|null $from = null,
        public string|UnitEnum|array $to = [],
        public ?string $guard = null,
        public array $metadata = [],
    ) {}
}
