<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use DateTimeImmutable;

final readonly class AuditEntry
{
    /**
     * @param list<string>         $from
     * @param list<string>         $to
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $machine,
        public string $subjectId,
        public string $transition,
        public array $from,
        public array $to,
        public ?string $actor,
        public DateTimeImmutable $occurredAt,
        public array $metadata = [],
    ) {}
}
