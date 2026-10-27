<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statecraft\Definition\Definition;
use Statecraft\Definition\DefinitionBuilder;
use Statecraft\Definition\DefinitionValidator;
use Statecraft\Definition\IssueCode;
use Statecraft\Definition\MachineType;
use Statecraft\Definition\Place;
use Statecraft\Definition\PlaceName;
use Statecraft\Definition\Transition;
use Statecraft\Definition\ValidationIssue;
use Statecraft\Exception\InvalidDefinition;
use Statecraft\Exception\UndefinedPlace;
use Statecraft\Exception\UndefinedTransition;
use Statecraft\Marking\Marking;
use Statecraft\Tests\Fixtures\Machines;
use Statecraft\Tests\Fixtures\OrderStatus;

#[CoversClass(Definition::class)]
#[CoversClass(DefinitionBuilder::class)]
#[CoversClass(DefinitionValidator::class)]
#[CoversClass(InvalidDefinition::class)]
#[CoversClass(PlaceName::class)]
#[CoversClass(Place::class)]
final class DefinitionTest extends TestCase
{
    public function testBuilderProducesQueryableDefinition(): void
    {
        $door = Machines::door();

        self::assertSame('door', $door->name);
        self::assertTrue($door->isStateMachine());
        self::assertSame(['closed', 'open', 'locked', 'broken'], $door->placeNames());
        self::assertSame(['closed'], $door->initialPlaces);
        self::assertTrue($door->isInitial('closed'));
        self::assertFalse($door->isInitial('open'));
        self::assertSame(['broken'], $door->finalPlaces());
        self::assertCount(5, $door->transitions());
        self::assertTrue($door->hasTransition('lock'));
        self::assertFalse($door->hasTransition('fly'));
        self::assertSame(['open', 'lock', 'break'], array_map(static fn(Transition $t): string => $t->name, $door->outgoing('closed')));
        self::assertSame([], $door->outgoing('broken'));
        self::assertTrue($door->hasPlace('open'));
        self::assertFalse($door->hasPlace('ajar'));
        self::assertSame('open', $door->place('open')->label());
        self::assertTrue($door->initialMarking()->equals(new Marking('closed')));
    }

    public function testUnknownLookupsThrowTypedExceptions(): void
    {
        $door = Machines::door();

        try {
            $door->transition('fly');
            self::fail('Expected exception');
        } catch (UndefinedTransition $e) {
            self::assertSame('fly', $e->transition);
            self::assertSame('Machine "door" has no transition named "fly".', $e->getMessage());
        }

        $this->expectException(UndefinedPlace::class);
        $this->expectExceptionMessage('Machine "door" has no state named "ajar".');
        $door->place('ajar');
    }

    public function testStateMachineFiresFromAnySourceAndWorkflowNeedsAll(): void
    {
        $door = Machines::door();
        $break = $door->transition('break');
        self::assertTrue($door->isEnabledIn($break, new Marking('locked')));
        self::assertFalse($door->isEnabledIn($door->transition('lock'), new Marking('open')));
        self::assertSame(['broken'], $door->fire($break, new Marking('locked'))->places());

        $approval = Machines::approval();
        $approve = $approval->transition('approve');
        self::assertFalse($approval->isEnabledIn($approve, new Marking('legal_ok', 'tech_review')));
        self::assertTrue($approval->isEnabledIn($approve, new Marking('legal_ok', 'tech_ok')));
        self::assertSame(
            ['legal_review', 'tech_review'],
            $approval->fire($approval->transition('submit'), new Marking('draft'))->places(),
        );
    }

    public function testBuilderAcceptsEnumsLabelsAndMetadata(): void
    {
        $definition = (new DefinitionBuilder('order', MachineType::StateMachine))
            ->initial(OrderStatus::New)
            ->place(OrderStatus::Paid, label: 'Paid', metadata: ['color' => 'green'])
            ->final(OrderStatus::Delivered)
            ->transition('pay', OrderStatus::New, OrderStatus::Paid)
            ->transition('deliver', [OrderStatus::Paid, OrderStatus::Paid], OrderStatus::Delivered)
            ->metadata(['a' => 1])
            ->metadata(['b' => 2])
            ->build();

        self::assertSame(['new', 'paid', 'delivered'], $definition->placeNames());
        self::assertSame('Paid', $definition->place('paid')->label());
        self::assertSame(['color' => 'green'], $definition->place('paid')->metadata);
        self::assertSame(['paid'], $definition->transition('deliver')->froms);
        self::assertSame(['a' => 1, 'b' => 2], $definition->metadata);
    }

    public function testInitialDoesNotRedeclareAKnownPlace(): void
    {
        $definition = Definition::workflow('w')
            ->places('a', 'b')
            ->initial('a', 'b')
            ->final('c')
            ->transition('join', ['a', 'b'], 'c')
            ->build();

        self::assertSame(['a', 'b', 'c'], $definition->placeNames());
        self::assertSame(MachineType::Workflow, $definition->type);
        self::assertSame(['a', 'b'], $definition->initialMarking()->places());
    }

    /**
     * @return iterable<string, array{DefinitionBuilder, list<IssueCode>}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'no states' => [Definition::stateMachine('m'), [IssueCode::NoPlaces]];

        yield 'no initial' => [
            Definition::stateMachine('m')->place('a', final: true),
            [IssueCode::MissingInitialPlace],
        ];

        yield 'two initial states in a state machine' => [
            Definition::stateMachine('m')->initial('a', 'b')->final('c')->transition('t', ['a', 'b'], 'c'),
            [IssueCode::MultipleInitialPlaces],
        ];

        yield 'duplicate state' => [
            Definition::stateMachine('m')->initial('a')->final('a'),
            [IssueCode::DuplicatePlace],
        ];

        yield 'duplicate transition' => [
            Definition::stateMachine('m')->initial('a')->final('b')->transition('go', 'a', 'b')->transition('go', 'a', 'b'),
            [IssueCode::DuplicateTransition],
        ];

        yield 'empty transition' => [
            Definition::stateMachine('m')->initial('a')->final('b')->transition('go', 'a', 'b')->transition('noop', [], 'b'),
            [IssueCode::EmptyTransition],
        ];

        yield 'unknown state in transition' => [
            Definition::stateMachine('m')->initial('a')->final('b')->transition('go', 'a', 'bb'),
            [IssueCode::UnknownPlace],
        ];

        yield 'fork in a state machine' => [
            Definition::stateMachine('m')->initial('a')->final('b', 'c')->transition('go', 'a', ['b', 'c']),
            [IssueCode::MultipleTargets],
        ];

        yield 'unreachable state' => [
            Definition::stateMachine('m')->initial('a')->place('island')->final('b')->transition('go', 'a', 'b')->transition('leave', 'island', 'b'),
            [IssueCode::UnreachablePlace],
        ];

        yield 'dead end' => [
            Definition::stateMachine('m')->initial('a')->place('stuck')->transition('go', 'a', 'stuck'),
            [IssueCode::DeadEnd],
        ];

        yield 'final state with exit' => [
            Definition::stateMachine('m')->initial('a')->final('b')->transition('go', 'a', 'b')->transition('back', 'b', 'a'),
            [IssueCode::FinalPlaceHasExit],
        ];

        yield 'workflow join with an unreachable input' => [
            Definition::workflow('w')->initial('a')->places('b')->final('c')->transition('join', ['a', 'b'], 'c'),
            [IssueCode::UnreachablePlace, IssueCode::UnreachablePlace],
        ];
    }

    /**
     * @param list<IssueCode> $expected
     */
    #[DataProvider('invalidDefinitions')]
    public function testValidationReportsEveryIssue(DefinitionBuilder $builder, array $expected): void
    {
        try {
            $builder->build();
            self::fail('Expected InvalidDefinition');
        } catch (InvalidDefinition $e) {
            self::assertSame($expected, array_map(static fn(ValidationIssue $i): IssueCode => $i->code, $e->issues));
            self::assertStringContainsString('Machine "', $e->getMessage());
            self::assertStringContainsString(\count($expected) . ' definition error(s)', $e->getMessage());
        }
    }

    public function testStructuralIssuesAreReportedTogetherWithoutGraphNoise(): void
    {
        $issues = (new DefinitionValidator())->validate(
            MachineType::StateMachine,
            [new Place('a'), new Place('b')],
            ['x'],
            [new Transition('go', ['a'], ['zz']), new Transition('go', ['a'], ['b'])],
        );

        self::assertSame(
            [IssueCode::UnknownInitialPlace, IssueCode::UnknownPlace, IssueCode::DuplicateTransition],
            array_map(static fn(ValidationIssue $i): IssueCode => $i->code, $issues),
        );
        self::assertSame('Transition "go" refers to undeclared state "zz".', $issues[1]->message);
        self::assertSame('go', $issues[1]->subject);
    }

    public function testDeadEndAndUnreachableMessagesAreSpecific(): void
    {
        $issues = (new DefinitionValidator())->validate(
            MachineType::StateMachine,
            [new Place('a'), new Place('b'), new Place('c', final: true)],
            ['a'],
            [new Transition('go', ['a'], ['b'])],
        );

        self::assertSame([
            'State "c" cannot be reached from the initial state.',
            'State "b" has no outgoing transitions and is not marked final (dead end).',
        ], array_map(static fn(ValidationIssue $i): string => $i->message, $issues));
    }

    public function testPlaceNameNormalisesAllReferenceKinds(): void
    {
        self::assertSame('paid', PlaceName::of(OrderStatus::Paid));
        self::assertSame('x', PlaceName::of('x'));
        self::assertSame('Monday', PlaceName::of(\Statecraft\Tests\Fixtures\Weekday::Monday));
        self::assertSame(['new', 'paid'], PlaceName::list([OrderStatus::New, 'paid', 'new']));
        self::assertSame(['new'], PlaceName::list(OrderStatus::New));
        self::assertSame(['a', 'b'], PlaceName::list(new \ArrayIterator(['a', 'b'])));
    }
}
