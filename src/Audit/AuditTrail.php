<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use Statecraft\Exception\AuditTrailError;

/**
 * Port for recording and reading the history of applied transitions.
 */
interface AuditTrail
{
    /**
     * @throws AuditTrailError
     */
    public function record(AuditEntry $entry): void;

    /**
     * Entries for one subject, oldest first.
     *
     * @return list<AuditEntry>
     *
     * @throws AuditTrailError
     */
    public function history(string $machine, string $subjectId): array;
}
