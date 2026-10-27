<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;
use Statecraft\Definition\MachineType;

/**
 * Class-constant flavour of the attribute mapping, as a workflow with a fork and a join.
 */
#[StateMachine(type: MachineType::Workflow)]
#[Transition('publish', from: [self::SPELL_CHECKED, self::FACT_CHECKED], to: self::PUBLISHED)]
final class ArticleStates
{
    #[State(initial: true)]
    #[Transition('submit', to: [self::SPELLING, self::FACTS])]
    public const string DRAFT = 'draft';

    #[State]
    #[Transition('check_spelling', to: self::SPELL_CHECKED)]
    public const string SPELLING = 'spelling';

    #[State]
    #[Transition('check_facts', to: self::FACT_CHECKED)]
    public const string FACTS = 'facts';

    #[State]
    public const string SPELL_CHECKED = 'spell_checked';

    #[State]
    public const string FACT_CHECKED = 'fact_checked';

    #[State(final: true)]
    public const string PUBLISHED = 'published';

    public const string NOT_A_STATE = 'ignored';
}
