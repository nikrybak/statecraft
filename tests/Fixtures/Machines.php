<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use Statecraft\Definition\Definition;

final class Machines
{
    public static function door(): Definition
    {
        return Definition::stateMachine('door')
            ->initial('closed')
            ->places('open', 'locked')
            ->final('broken')
            ->transition('open', 'closed', 'open')
            ->transition('close', 'open', 'closed')
            ->transition('lock', 'closed', 'locked')
            ->transition('unlock', 'locked', 'closed')
            ->transition('break', ['closed', 'open', 'locked'], 'broken')
            ->build();
    }

    public static function approval(): Definition
    {
        return Definition::workflow('approval')
            ->initial('draft')
            ->places('legal_review', 'tech_review', 'legal_ok', 'tech_ok')
            ->final('approved', 'rejected')
            ->transition('submit', 'draft', ['legal_review', 'tech_review'])
            ->transition('approve_legal', 'legal_review', 'legal_ok')
            ->transition('approve_tech', 'tech_review', 'tech_ok')
            ->transition('reject', ['legal_review', 'tech_review'], 'rejected')
            ->transition('approve', ['legal_ok', 'tech_ok'], 'approved')
            ->build();
    }
}
