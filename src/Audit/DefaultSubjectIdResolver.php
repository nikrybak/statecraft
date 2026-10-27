<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use Statecraft\Exception\AuditTrailError;
use Stringable;

/**
 * Uses getId(), then a public $id property. Anything else needs a custom resolver,
 * because falling back to spl_object_id() would produce ids that mean nothing after the request.
 */
final class DefaultSubjectIdResolver implements SubjectIdResolver
{
    public function idOf(object $subject): string
    {
        $id = match (true) {
            method_exists($subject, 'getId') => $subject->getId(),
            property_exists($subject, 'id') && isset($subject->id) => $subject->id,
            default => null,
        };

        if (\is_string($id) || \is_int($id) || $id instanceof Stringable) {
            return (string) $id;
        }

        throw new AuditTrailError(\sprintf(
            'Cannot determine the id of %s for the audit trail; add getId() or pass a %s.',
            $subject::class,
            SubjectIdResolver::class,
        ));
    }
}
