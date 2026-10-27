<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Statecraft\Attribute\AttributeLoader;
use Statecraft\Audit\AuditEntry;
use Statecraft\Audit\Clock;
use Statecraft\Audit\InMemoryAuditTrail;
use Statecraft\Definition\Definition;
use Statecraft\Event\GuardFailed;
use Statecraft\Event\TransitionBlocked;
use Statecraft\Event\TransitionCompleted;
use Statecraft\Event\TransitionEvent;
use Statecraft\Event\TransitionStarted;
use Statecraft\Exception\TransitionNotAllowed;
use Statecraft\Exception\UndefinedTransition;
use Statecraft\Guard\GuardResolver;
use Statecraft\Guard\GuardResult;
use Statecraft\Machine;
use Statecraft\Marking\Marking;
use Statecraft\Marking\PropertyMarkingStore;
use Statecraft\Tests\Fixtures\Machines;
use Statecraft\Tests\Fixtures\Order;
use Statecraft\Tests\Fixtures\OrderStatus;
use Statecraft\Tests\Fixtures\RecordingDispatcher;
use Statecraft\TransitionBlocker;
use Statecraft\TransitionContext;

#[CoversClass(Machine::class)]
#[CoversClass(TransitionContext::class)]
#[CoversClass(TransitionBlocker::class)]
#[CoversClass(TransitionNotAllowed::class)]
#[CoversClass(TransitionEvent::class)]
#[CoversClass(TransitionStarted::class)]
#[CoversClass(TransitionCompleted::class)]
#[CoversClass(TransitionBlocked::class)]
#[CoversClass(GuardFailed::class)]
final class MachineTest extends TestCase
{
    private RecordingDispatcher $events;

    private InMemoryAuditTrail $audit;

    protected function setUp(): void
    {
        $this->events = new RecordingDispatcher();
        $this->audit = new InMemoryAuditTrail();
    }

    public function testAppliesTransitionsAndWritesTheSubject(): void
    {
        $machine = $this->orderMachine();
        $order = new Order();

        self::assertSame('new', $machine->state($order));
        self::assertNull($order->status, 'reading the initial state must not write it');

        $after = $machine->apply($order, 'pay');

        self::assertSame(['paid'], $after->places());
        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertSame('paid', $machine->state($order));
        self::assertFalse($machine->isFinished($order));

        $machine->apply($order, 'ship');
        $machine->apply($order, 'deliver');
        self::assertTrue($machine->isFinished($order));
        self::assertSame([], $machine->enabledTransitions($order));
    }

    public function testInitializeWritesInitialMarkingOnce(): void
    {
        $machine = $this->orderMachine();
        $order = new Order();

        self::assertSame(['new'], $machine->initialize($order)->places());
        self::assertSame(OrderStatus::New, $order->status);

        $order->status = OrderStatus::Paid;
        self::assertSame(['paid'], $machine->initialize($order)->places());
    }

    public function testEventsAndHooksRunInDocumentedOrder(): void
    {
        $machine = $this->orderMachine();
        $log = &$this->events->log;
        $machine
            ->before(Machine::ANY, static function (TransitionContext $c) use (&$log): void {
                $log[] = 'before:*:' . $c->transition->name;
            })
            ->before('pay', static function (TransitionContext $c) use (&$log): void {
                $log[] = 'before:pay:' . ($c->subject instanceof Order && $c->subject->status === null ? 'unwritten' : 'written');
            })
            ->after('pay', static function (TransitionContext $c, Marking $m) use (&$log): void {
                $log[] = 'after:pay:' . $m->toString();
            })
            ->after(Machine::ANY, static function () use (&$log): void {
                $log[] = 'after:*';
            });

        $machine->apply(new Order(), 'pay', ['note' => 'x'], 'alice');

        self::assertSame([
            'event:TransitionStarted',
            'before:*:pay',
            'before:pay:unwritten',
            'after:*',
            'after:pay:paid',
            'event:TransitionCompleted',
        ], $this->events->log);

        $completed = $this->events->events[1];
        self::assertInstanceOf(TransitionCompleted::class, $completed);
        self::assertSame('order', $completed->machine());
        self::assertSame('pay', $completed->transition()->name);
        self::assertSame(['new'], $completed->markingBefore()->places());
        self::assertSame(['paid'], $completed->markingAfter->places());
        self::assertSame('alice', $completed->context->actor);
        self::assertSame('x', $completed->context->get('note'));
        self::assertSame('fallback', $completed->context->get('missing', 'fallback'));
        self::assertInstanceOf(Order::class, $completed->subject());
    }

    public function testGuardFailureDispatchesEventsAndThrowsWithReasons(): void
    {
        $machine = $this->orderMachine();
        $order = new Order(amount: 0);

        self::assertFalse($machine->can($order, 'pay'));
        self::assertSame([], $this->events->events, 'queries must not dispatch events');
        self::assertSame(['cancel'], array_map(static fn($t): string => $t->name, $machine->enabledTransitions($order)));

        try {
            $machine->apply($order, 'pay');
            self::fail('Expected exception');
        } catch (TransitionNotAllowed $e) {
            self::assertSame('pay', $e->transition);
            self::assertSame(TransitionBlocker::GUARD_FAILED, $e->blockers[0]->code);
            self::assertSame(
                'Transition "pay" of machine "order" is not allowed: guard of "pay" rejected it: order amount must be positive',
                $e->getMessage(),
            );
        }

        self::assertSame(['event:GuardFailed', 'event:TransitionBlocked'], $this->events->log);
        $guardFailed = $this->events->events[0];
        self::assertInstanceOf(GuardFailed::class, $guardFailed);
        self::assertSame('order amount must be positive', $guardFailed->reason());
        $blocked = $this->events->events[1];
        self::assertInstanceOf(TransitionBlocked::class, $blocked);
        self::assertCount(1, $blocked->blockers);
        self::assertNull($order->status);
        self::assertSame([], $this->audit->all());
    }

    public function testTransitionNotEnabledInMarkingSkipsGuards(): void
    {
        $guardCalls = 0;
        $definition = Definition::stateMachine('m')
            ->initial('a')->place('b')->final('c')
            ->transition('ab', 'a', 'b')
            ->transition('bc', 'b', 'c', static function () use (&$guardCalls): bool {
                ++$guardCalls;

                return true;
            })
            ->build();
        $machine = new Machine($definition, events: $this->events);
        $subject = new class {
            public ?string $state = null;
        };

        self::assertSame(
            ['"bc" cannot fire from the current marking [a]'],
            array_map(static fn(TransitionBlocker $b): string => $b->message, $machine->blockers($subject, 'bc')),
        );
        self::assertSame(0, $guardCalls);

        $this->expectException(TransitionNotAllowed::class);
        try {
            $machine->apply($subject, 'bc');
        } finally {
            self::assertSame(['event:TransitionBlocked'], $this->events->log);
        }
    }

    public function testBeforeHookCanAbortWithoutChangingTheSubject(): void
    {
        $machine = $this->orderMachine()->before('pay', static function (): void {
            throw new RuntimeException('payment gateway down');
        });
        $order = new Order();

        try {
            $machine->apply($order, 'pay');
            self::fail('Expected exception');
        } catch (RuntimeException $e) {
            self::assertSame('payment gateway down', $e->getMessage());
        }
        self::assertNull($order->status);
        self::assertSame([], $this->audit->all());
        self::assertSame(['event:TransitionStarted'], $this->events->log);
    }

    public function testRecordsAuditEntries(): void
    {
        $machine = $this->orderMachine();
        $order = new Order('o-7');

        $machine->apply($order, 'pay', ['receipt' => 'r1'], 'cashier');
        $machine->apply($order, 'cancel');

        $history = $this->audit->history('order', 'o-7');
        self::assertCount(2, $history);
        self::assertEquals(
            new AuditEntry('order', 'o-7', 'pay', ['new'], ['paid'], 'cashier', new DateTimeImmutable('2026-01-01 10:00:00'), ['receipt' => 'r1']),
            $history[0],
        );
        self::assertSame(['paid'], $history[1]->from);
        self::assertSame(['cancelled'], $history[1]->to);
        self::assertNull($history[1]->actor);
        self::assertSame([], $this->audit->history('order', 'other'));
        self::assertSame([], $this->audit->history('invoice', 'o-7'));
    }

    public function testWorkflowMarksSeveralPlaces(): void
    {
        $machine = new Machine(Machines::approval());
        $document = new class {
            /** @var list<string>|null */
            public ?array $state = null;
        };

        self::assertSame(['submit'], array_map(static fn($t): string => $t->name, $machine->enabledTransitions($document)));
        $machine->apply($document, 'submit');
        self::assertSame(['legal_review', 'tech_review'], $document->state);
        self::assertTrue($machine->can($document, 'reject'));
        self::assertFalse($machine->can($document, 'approve'));

        $machine->apply($document, 'approve_legal');
        self::assertFalse($machine->can($document, 'reject'), 'reject is a join over both reviews');
        self::assertFalse($machine->can($document, 'approve'));
        $machine->apply($document, 'approve_tech');
        self::assertFalse($machine->isFinished($document));
        self::assertSame(['approved'], $machine->apply($document, 'approve')->places());
        self::assertTrue($machine->isFinished($document));
    }

    public function testRejectsUnknownTransitionsEverywhere(): void
    {
        $machine = $this->orderMachine();

        foreach ([
            static fn() => $machine->apply(new Order(), 'refund'),
            static fn() => $machine->can(new Order(), 'refund'),
            static fn() => $machine->before('refund', static function (): void {}),
            static fn() => $machine->after('refund', static function (): void {}),
        ] as $call) {
            try {
                $call();
                self::fail('Expected exception');
            } catch (UndefinedTransition $e) {
                self::assertSame('refund', $e->transition);
                self::assertSame('order', $e->machine);
            }
        }
        self::assertSame('order', $machine->definition()->name);
    }

    public function testClosureGuardsSeeContext(): void
    {
        $definition = Definition::stateMachine('m')
            ->initial('a')->final('b')
            ->transition('go', 'a', 'b', static fn(TransitionContext $c): GuardResult => GuardResult::when($c->actor === 'admin', 'admins only'))
            ->build();
        $machine = new Machine($definition, guards: new GuardResolver());
        $subject = new class {
            public ?string $state = null;
        };

        self::assertFalse($machine->can($subject, 'go', actor: 'guest'));
        self::assertTrue($machine->can($subject, 'go', actor: 'admin'));
    }

    private function orderMachine(): Machine
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01 10:00:00');
            }
        };

        return new Machine(
            (new AttributeLoader())->load(OrderStatus::class),
            new PropertyMarkingStore('status'),
            $this->events,
            audit: $this->audit,
            clock: $clock,
        );
    }
}
