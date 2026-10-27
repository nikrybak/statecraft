<?php

declare(strict_types=1);

namespace Statecraft\Attribute;

use Attribute;

/**
 * Configures an enum case, or declares a class constant as a state.
 *
 * On an enum every case is a state even without this attribute; on a plain class
 * only constants carrying it are.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class State
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $initial = false,
        public bool $final = false,
        public ?string $label = null,
        public array $metadata = [],
    ) {}
}
