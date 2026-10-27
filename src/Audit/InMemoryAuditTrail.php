<?php

declare(strict_types=1);

namespace Statecraft\Audit;

/**
 * Keeps entries in process memory. Meant for tests and short-lived scripts.
 */
final class InMemoryAuditTrail implements AuditTrail
{
    /** @var list<AuditEntry> */
    private array $entries = [];

    public function record(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function history(string $machine, string $subjectId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn(AuditEntry $e): bool => $e->machine === $machine && $e->subjectId === $subjectId,
        ));
    }

    /**
     * @return list<AuditEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
