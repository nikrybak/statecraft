<?php

declare(strict_types=1);

namespace Statecraft\Attribute;

use Attribute;
use Statecraft\Definition\MachineType;

/**
 * Marks an enum or class as a machine definition. Required for CLI discovery,
 * optional when the class is passed to AttributeLoader directly.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StateMachine
{
    /**
     * @param string|null          $name     defaults to the snake_cased short class name
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ?string $name = null,
        public MachineType $type = MachineType::StateMachine,
        public array $metadata = [],
    ) {}
}
