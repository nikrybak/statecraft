<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Marking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statecraft\Definition\Definition;
use Statecraft\Exception\InvalidMarking;
use Statecraft\Exception\MarkingStoreError;
use Statecraft\Marking\Marking;
use Statecraft\Marking\MarkingCodec;
use Statecraft\Marking\MethodMarkingStore;
use Statecraft\Marking\PropertyMarkingStore;
use Statecraft\Tests\Fixtures\Machines;
use Statecraft\Tests\Fixtures\Order;
use Statecraft\Tests\Fixtures\OrderStatus;
use Statecraft\Tests\Fixtures\Weekday;
use stdClass;

#[CoversClass(Marking::class)]
#[CoversClass(MarkingCodec::class)]
#[CoversClass(PropertyMarkingStore::class)]
#[CoversClass(MethodMarkingStore::class)]
final class MarkingStoreTest extends TestCase
{
    public function testMarkingIsAnImmutableSortedSet(): void
    {
        $marking = new Marking('b', 'a', 'b');

        self::assertSame(['a', 'b'], $marking->places());
        self::assertCount(2, $marking);
        self::assertTrue($marking->has('a'));
        self::assertFalse($marking->has('c'));
        self::assertSame(['a', 'b', 'c'], $marking->with('c')->places());
        self::assertSame(['b'], $marking->without('a', 'x')->places());
        self::assertSame(['a', 'b'], $marking->places());
        self::assertTrue($marking->equals(new Marking('a', 'b')));
        self::assertFalse($marking->equals(new Marking('a')));
        self::assertSame('a, b', $marking->toString());
        self::assertTrue((new Marking())->isEmpty());
        self::assertFalse($marking->isEmpty());
        self::assertSame('a', (new Marking('a'))->single());

        $this->expectException(InvalidMarking::class);
        $this->expectExceptionMessage('Expected exactly one marked state, got 2 [a, b].');
        $marking->single();
    }

    public function testPropertyStoreRoundTripsEnumTypedProperty(): void
    {
        $definition = $this->orderDefinition();
        $store = new PropertyMarkingStore('status');
        $order = new Order();

        self::assertTrue($store->read($definition, $order)->isEmpty());

        $store->write($definition, $order, new Marking('paid'));
        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertSame(['paid'], $store->read($definition, $order)->places());
    }

    public function testPropertyStoreHandlesPrivateUntypedAndUninitialisedProperties(): void
    {
        $definition = Machines::door();
        $store = new PropertyMarkingStore();

        $subject = new class {
            private string $state;

            public function state(): string
            {
                return $this->state;
            }
        };
        self::assertTrue($store->read($definition, $subject)->isEmpty());
        $store->write($definition, $subject, new Marking('open'));
        self::assertSame('open', $subject->state());

        $untyped = new class {
            /** @var mixed */
            public $state = 'locked';
        };
        self::assertSame(['locked'], $store->read($definition, $untyped)->places());
    }

    public function testPropertyStoreWritesWorkflowMarkingAsList(): void
    {
        $definition = Machines::approval();
        $subject = new class {
            /** @var mixed */
            public $state = ['legal_review' => true, 'tech_review' => 1, 'draft' => false];
        };
        $store = new PropertyMarkingStore();

        self::assertSame(['legal_review', 'tech_review'], $store->read($definition, $subject)->places());

        $store->write($definition, $subject, new Marking('tech_ok', 'legal_ok'));
        self::assertSame(['legal_ok', 'tech_ok'], $subject->state);
        self::assertSame(['legal_ok', 'tech_ok'], $store->read($definition, $subject)->places());
    }

    public function testPropertyStoreReportsMissingPropertyAndFailedWrites(): void
    {
        $store = new PropertyMarkingStore('nope');

        try {
            $store->read(Machines::door(), new stdClass());
            self::fail('Expected exception');
        } catch (MarkingStoreError $e) {
            self::assertSame('Subject stdClass has no property "nope" to hold its state.', $e->getMessage());
        }

        $readonly = new class {
            public function __construct(public readonly string $state = 'open') {}
        };
        $this->expectException(MarkingStoreError::class);
        $this->expectExceptionMessageMatches('/Cannot write state to .*::\$state/');
        (new PropertyMarkingStore())->write(Machines::door(), $readonly, new Marking('closed'));
    }

    public function testCodecRejectsValuesThatDoNotFitTheDefinition(): void
    {
        $door = Machines::door();

        foreach ([
            'unknown state' => ['ajar', 'Subject is in state "ajar", which machine "door" does not define.'],
            'wrong type' => [42, 'Machine "door" cannot read a marking from a value of type int.'],
            'two states' => [['open', 'closed'], 'State machine "door" read 2 marked states from the subject; expected one.'],
            'bad list item' => [[1.5], 'Marking list items must be strings or enum cases, got float.'],
        ] as $case => [$raw, $message]) {
            try {
                MarkingCodec::decode($door, $raw);
                self::fail('Expected exception for ' . $case);
            } catch (InvalidMarking $e) {
                self::assertSame($message, $e->getMessage(), $case);
            }
        }
    }

    public function testCodecConvertsToPureAndIntBackedEnums(): void
    {
        $days = Definition::stateMachine('days')->initial(Weekday::Monday)->final(Weekday::Tuesday)
            ->transition('next', Weekday::Monday, Weekday::Tuesday)->build();
        $subject = new class {
            public ?Weekday $state = Weekday::Monday;
        };
        $store = new PropertyMarkingStore();
        self::assertSame(['Monday'], $store->read($days, $subject)->places());
        $store->write($days, $subject, new Marking('Tuesday'));
        self::assertSame(Weekday::Tuesday, $subject->state);

        $levels = Definition::stateMachine('levels')->initial('1')->final('2')->transition('up', '1', '2')->build();
        $leveled = new class {
            public ?\Statecraft\Tests\Fixtures\Level $state = null;
        };
        $store->write($levels, $leveled, new Marking('2'));
        self::assertSame(\Statecraft\Tests\Fixtures\Level::Two, $leveled->state);

        $this->expectException(InvalidMarking::class);
        $this->expectExceptionMessage('State "Sunday" has no matching case in enum');
        $odd = Definition::stateMachine('odd')->initial('Monday')->final('Sunday')->transition('skip', 'Monday', 'Sunday')->build();
        $store->write($odd, $subject, new Marking('Sunday'));
    }

    public function testMethodStoreUsesGetterAndSetter(): void
    {
        $definition = $this->orderDefinition();
        $store = new MethodMarkingStore('getStatus', 'setStatus');
        $subject = new class {
            private ?OrderStatus $status = null;

            public function getStatus(): ?OrderStatus
            {
                return $this->status;
            }

            public function setStatus(OrderStatus $status): void
            {
                $this->status = $status;
            }
        };

        self::assertTrue($store->read($definition, $subject)->isEmpty());
        $store->write($definition, $subject, new Marking('shipped'));
        self::assertSame(OrderStatus::Shipped, $subject->getStatus());
        self::assertSame(['shipped'], $store->read($definition, $subject)->places());
    }

    public function testMethodStoreReportsUnusableMethods(): void
    {
        $door = Machines::door();
        $subject = new class {
            public function getState(): string
            {
                return 'open';
            }

            public function setState(): void {}

            private function hidden(): string
            {
                return 'open';
            }
        };

        foreach ([
            [new MethodMarkingStore('missing'), 'read', '/has no method missing\(\)/'],
            [new MethodMarkingStore('hidden'), 'read', '/::hidden\(\) must be public/'],
            [new MethodMarkingStore(), 'write', '/::setState\(\) must accept the new state/'],
        ] as [$store, $operation, $pattern]) {
            try {
                $operation === 'read' ? $store->read($door, $subject) : $store->write($door, $subject, new Marking('open'));
                self::fail('Expected exception');
            } catch (MarkingStoreError $e) {
                self::assertMatchesRegularExpression($pattern, $e->getMessage());
            }
        }
    }

    private function orderDefinition(): Definition
    {
        return (new \Statecraft\Attribute\AttributeLoader())->load(OrderStatus::class);
    }
}
