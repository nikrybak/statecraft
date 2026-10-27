<?php

declare(strict_types=1);

namespace Statecraft;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Statecraft\Audit\AuditEntry;
use Statecraft\Audit\AuditTrail;
use Statecraft\Audit\Clock;
use Statecraft\Audit\DefaultSubjectIdResolver;
use Statecraft\Audit\SubjectIdResolver;
use Statecraft\Audit\SystemClock;
use Statecraft\Definition\Definition;
use Statecraft\Definition\Transition;
use Statecraft\Event\GuardFailed;
use Statecraft\Event\TransitionBlocked;
use Statecraft\Event\TransitionCompleted;
use Statecraft\Event\TransitionStarted;
use Statecraft\Exception\GuardResolutionFailed;
use Statecraft\Exception\InvalidMarking;
use Statecraft\Exception\MarkingStoreError;
use Statecraft\Exception\TransitionNotAllowed;
use Statecraft\Exception\UndefinedTransition;
use Statecraft\Guard\GuardResolver;
use Statecraft\Guard\GuardResult;
use Statecraft\Marking\Marking;
use Statecraft\Marking\MarkingStore;
use Statecraft\Marking\PropertyMarkingStore;

/**
 * Applies the transitions of one Definition to subjects.
 *
 * Order of an apply(): structural check, guards, TransitionStarted, before hooks, marking write,
 * audit record, after hooks, TransitionCompleted. Anything that throws before the marking write
 * leaves the subject untouched. Wrap apply() in a database transaction when the audit trail
 * and the subject share a database and both must commit together.
 */
final class Machine
{
    public const string ANY = '*';

    /** @var array<string, list<Closure(TransitionContext): void>> */
    private array $before = [];

    /** @var array<string, list<Closure(TransitionContext, Marking): void>> */
    private array $after = [];

    public function __construct(
        private readonly Definition $definition,
        private readonly MarkingStore $markingStore = new PropertyMarkingStore(),
        private readonly ?EventDispatcherInterface $events = null,
        private readonly GuardResolver $guards = new GuardResolver(),
        private readonly ?AuditTrail $audit = null,
        private readonly SubjectIdResolver $subjectIds = new DefaultSubjectIdResolver(),
        private readonly Clock $clock = new SystemClock(),
    ) {}

    public function definition(): Definition
    {
        return $this->definition;
    }

    /**
     * Registers a hook that runs after guards pass and before the marking changes.
     * Throwing from it aborts the transition.
     *
     * @param Closure(TransitionContext): void $hook
     *
     * @throws UndefinedTransition
     */
    public function before(string $transition, Closure $hook): self
    {
        $this->before[$this->hookKey($transition)][] = $hook;

        return $this;
    }

    /**
     * Registers a hook that runs once the new marking has been written and recorded.
     *
     * @param Closure(TransitionContext, Marking): void $hook
     *
     * @throws UndefinedTransition
     */
    public function after(string $transition, Closure $hook): self
    {
        $this->after[$this->hookKey($transition)][] = $hook;

        return $this;
    }

    /**
     * The current marking, or the initial one when the subject has no state yet.
     *
     * @throws InvalidMarking
     * @throws MarkingStoreError
     */
    public function marking(object $subject): Marking
    {
        $marking = $this->markingStore->read($this->definition, $subject);

        return $marking->isEmpty() ? $this->definition->initialMarking() : $marking;
    }

    /**
     * The single current state of a state machine subject.
     *
     * @throws InvalidMarking
     */
    public function state(object $subject): string
    {
        return $this->marking($subject)->single();
    }

    /**
     * Writes the initial marking when the subject has none yet; returns the current marking.
     */
    public function initialize(object $subject): Marking
    {
        $marking = $this->markingStore->read($this->definition, $subject);
        if (!$marking->isEmpty()) {
            return $marking;
        }

        $initial = $this->definition->initialMarking();
        $this->markingStore->write($this->definition, $subject, $initial);

        return $initial;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws UndefinedTransition
     */
    public function can(object $subject, string $transition, array $metadata = [], ?string $actor = null): bool
    {
        return $this->blockers($subject, $transition, $metadata, $actor) === [];
    }

    /**
     * Every reason the transition cannot fire right now; empty when it can.
     *
     * @param array<string, mixed> $metadata
     *
     * @return list<TransitionBlocker>
     *
     * @throws UndefinedTransition
     * @throws GuardResolutionFailed
     */
    public function blockers(object $subject, string $transition, array $metadata = [], ?string $actor = null): array
    {
        $context = $this->context($subject, $this->definition->transition($transition), $metadata, $actor);
        [$blockers] = $this->evaluate($context);

        return $blockers;
    }

    /**
     * Transitions that can fire for the subject now, guards included.
     *
     * @param array<string, mixed> $metadata
     *
     * @return list<Transition>
     */
    public function enabledTransitions(object $subject, array $metadata = [], ?string $actor = null): array
    {
        $marking = $this->marking($subject);
        $enabled = [];
        foreach ($this->definition->transitions() as $transition) {
            [$blockers] = $this->evaluate(new TransitionContext($this->definition, $subject, $transition, $marking, $actor, $metadata));
            if ($blockers === []) {
                $enabled[] = $transition;
            }
        }

        return $enabled;
    }

    /**
     * Whether every marked place is a final state.
     */
    public function isFinished(object $subject): bool
    {
        foreach ($this->marking($subject)->places() as $place) {
            if (!$this->definition->place($place)->final) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $metadata stored in the audit trail and visible to guards, hooks and listeners
     *
     * @return Marking the marking after the transition
     *
     * @throws UndefinedTransition
     * @throws TransitionNotAllowed
     * @throws GuardResolutionFailed
     */
    public function apply(object $subject, string $transition, array $metadata = [], ?string $actor = null): Marking
    {
        $context = $this->context($subject, $this->definition->transition($transition), $metadata, $actor);

        [$blockers, $failedGuard] = $this->evaluate($context);
        if ($failedGuard !== null) {
            $this->events?->dispatch(new GuardFailed($context, $failedGuard));
        }
        if ($blockers !== []) {
            $this->events?->dispatch(new TransitionBlocked($context, $blockers));

            throw new TransitionNotAllowed($this->definition->name, $transition, $blockers);
        }

        $this->events?->dispatch(new TransitionStarted($context));
        $this->runBefore($context);

        $next = $this->definition->fire($context->transition, $context->marking);
        $this->markingStore->write($this->definition, $subject, $next);

        $this->audit?->record(new AuditEntry(
            machine: $this->definition->name,
            subjectId: $this->subjectIds->idOf($subject),
            transition: $transition,
            from: $context->marking->places(),
            to: $next->places(),
            actor: $actor,
            occurredAt: $this->clock->now(),
            metadata: $metadata,
        ));

        $this->runAfter($context, $next);
        $this->events?->dispatch(new TransitionCompleted($context, $next));

        return $next;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function context(object $subject, Transition $transition, array $metadata, ?string $actor): TransitionContext
    {
        return new TransitionContext($this->definition, $subject, $transition, $this->marking($subject), $actor, $metadata);
    }

    /**
     * Guards are skipped for transitions the marking does not enable, so a guard can assume
     * the subject is in one of the transition's source states.
     *
     * @return array{list<TransitionBlocker>, ?GuardResult}
     */
    private function evaluate(TransitionContext $context): array
    {
        $transition = $context->transition;
        if (!$this->definition->isEnabledIn($transition, $context->marking)) {
            return [[TransitionBlocker::notEnabled($transition->name, $context->marking->toString())], null];
        }
        if ($transition->guard === null) {
            return [[], null];
        }

        $result = $this->guards->resolve($transition->guard)->check($context);
        if ($result->allowed) {
            return [[], null];
        }

        return [[TransitionBlocker::guardFailed($transition->name, $result->reason)], $result];
    }

    private function runBefore(TransitionContext $context): void
    {
        foreach ([...$this->before[self::ANY] ?? [], ...$this->before[$context->transition->name] ?? []] as $hook) {
            $hook($context);
        }
    }

    private function runAfter(TransitionContext $context, Marking $next): void
    {
        foreach ([...$this->after[self::ANY] ?? [], ...$this->after[$context->transition->name] ?? []] as $hook) {
            $hook($context, $next);
        }
    }

    private function hookKey(string $transition): string
    {
        if ($transition !== self::ANY) {
            $this->definition->transition($transition);
        }

        return $transition;
    }
}
