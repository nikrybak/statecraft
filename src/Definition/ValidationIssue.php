<?php

declare(strict_types=1);

namespace Statecraft\Definition;

final readonly class ValidationIssue
{
    public function __construct(
        public IssueCode $code,
        public string $message,
        public ?string $subject = null,
    ) {}
}
