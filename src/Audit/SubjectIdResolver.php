<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use Statecraft\Exception\AuditTrailError;

/**
 * Tells the audit trail which subject an entry belongs to.
 */
interface SubjectIdResolver
{
    /**
     * @throws AuditTrailError when the subject has no usable identity
     */
    public function idOf(object $subject): string;
}
