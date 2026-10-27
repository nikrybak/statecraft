<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statecraft\Attribute\AttributeLoader;
use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;
use Statecraft\Definition\MachineType;
use Statecraft\Exception\AttributeMappingError;
use Statecraft\Exception\InvalidDefinition;
use Statecraft\Tests\Fixtures\ArticleStates;
use Statecraft\Tests\Fixtures\Order;
use Statecraft\Tests\Fixtures\OrderStatus;
use Statecraft\Tests\Fixtures\PositiveAmount;

#[CoversClass(AttributeLoader::class)]
#[CoversClass(State::class)]
#[CoversClass(StateMachine::class)]
#[CoversClass(Transition::class)]
final class AttributeLoaderTest extends TestCase
{
    public function testLoadsEnumWithCaseAndClassLevelTransitions(): void
    {
        $definition = (new AttributeLoader())->load(OrderStatus::class);

        self::assertSame('order', $definition->name);
        self::assertSame(MachineType::StateMachine, $definition->type);
        self::assertSame(['new', 'paid', 'shipped', 'delivered', 'cancelled'], $definition->placeNames());
        self::assertSame(['new'], $definition->initialPlaces);
        self::assertSame(['delivered', 'cancelled'], $definition->finalPlaces());
        self::assertSame('New order', $definition->place('new')->label());
        self::assertSame(['pay', 'ship', 'deliver', 'cancel'], array_map(static fn($t): string => $t->name, $definition->transitions()));
        self::assertSame(['new'], $definition->transition('pay')->froms);
        self::assertSame(PositiveAmount::class, $definition->transition('pay')->guard);
        self::assertTrue($definition->transition('pay')->hasGuard());
        self::assertFalse($definition->transition('ship')->hasGuard());
        self::assertSame(['new', 'paid'], $definition->transition('cancel')->froms);
        self::assertSame(['owner' => 'sales', 'class' => OrderStatus::class], $definition->metadata);
    }

    public function testLoadsClassConstantsAsWorkflow(): void
    {
        $definition = (new AttributeLoader())->load(ArticleStates::class);

        self::assertSame('article_states', $definition->name);
        self::assertSame(MachineType::Workflow, $definition->type);
        self::assertNotContains('ignored', $definition->placeNames());
        self::assertSame(['spelling', 'facts'], $definition->transition('submit')->tos);
        self::assertSame(['spell_checked', 'fact_checked'], $definition->transition('publish')->froms);
    }

    public function testCachesDefinitionsPerClass(): void
    {
        $loader = new AttributeLoader();

        self::assertSame($loader->load(OrderStatus::class), $loader->load(OrderStatus::class));
    }

    public function testDetectsMachineClasses(): void
    {
        self::assertTrue(AttributeLoader::isMachine(OrderStatus::class));
        self::assertFalse(AttributeLoader::isMachine(Order::class));
    }

    public function testClassLevelTransitionNeedsFrom(): void
    {
        $this->expectException(AttributeMappingError::class);
        $this->expectExceptionMessage('Transition "go" on class ' . MissingFrom::class . ' needs "from"');

        (new AttributeLoader())->load(MissingFrom::class);
    }

    public function testStateConstantMustBeStringOrEnum(): void
    {
        $this->expectException(AttributeMappingError::class);
        $this->expectExceptionMessage('State constant ' . IntegerState::class . '::ONE must be a string or an enum case, got int.');

        (new AttributeLoader())->load(IntegerState::class);
    }

    public function testInvalidMappingSurfacesDefinitionErrors(): void
    {
        try {
            (new AttributeLoader())->load(NoInitial::class);
            self::fail('Expected exception');
        } catch (InvalidDefinition $e) {
            self::assertSame('no_initial', $e->machine);
            self::assertStringContainsString('No initial state is declared.', $e->getMessage());
        }
    }
}

#[Transition('go', to: 'b')]
final class MissingFrom
{
    #[State(initial: true)]
    public const string A = 'a';

    #[State(final: true)]
    public const string B = 'b';
}

final class IntegerState
{
    #[State(initial: true)]
    public const int ONE = 1;
}

enum NoInitial
{
    #[Transition('go', to: self::B)]
    case A;

    #[State(final: true)]
    case B;
}
