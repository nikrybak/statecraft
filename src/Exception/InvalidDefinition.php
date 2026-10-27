<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use DomainException;
use Statecraft\Definition\ValidationIssue;

final class InvalidDefinition extends DomainException implements StatecraftException
{
    /**
     * @param non-empty-list<ValidationIssue> $issues
     */
    public function __construct(
        public readonly string $machine,
        public readonly array $issues,
    ) {
        $lines = array_map(static fn(ValidationIssue $issue): string => '  - ' . $issue->message, $issues);

        parent::__construct(\sprintf(
            "Machine \"%s\" has %d definition error(s):\n%s",
            $machine,
            \count($issues),
            implode("\n", $lines),
        ));
    }
}
