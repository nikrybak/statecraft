<?php

declare(strict_types=1);

namespace Statecraft\Definition;

final readonly class Place
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $name,
        public bool $final = false,
        public ?string $label = null,
        public array $metadata = [],
    ) {}

    public function label(): string
    {
        return $this->label ?? $this->name;
    }
}
