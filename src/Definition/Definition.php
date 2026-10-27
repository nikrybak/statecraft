<?php

declare(strict_types=1);

namespace Statecraft\Definition;

use Statecraft\Exception\InvalidDefinition;
use Statecraft\Exception\UndefinedPlace;
use Statecraft\Exception\UndefinedTransition;
use Statecraft\Marking\Marking;

/**
 * Immutable, validated description of a machine. Constructing one with structural errors
 * throws, so any Definition instance in the program is known to be consistent.
 */
final readonly class Definition
{
    /** @var array<string, Place> */
    private array $places;

    /** @var array<string, Transition> */
    private array $transitions;

    /** @var array<string, list<Transition>> */
    private array $outgoing;

    /**
     * @param list<Place>          $places
     * @param list<string>         $initialPlaces
     * @param list<Transition>     $transitions
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidDefinition
     */
    public function __construct(
        public string $name,
        public MachineType $type,
        array $places,
        public array $initialPlaces,
        array $transitions,
        public array $metadata = [],
    ) {
        $issues = (new DefinitionValidator())->validate($type, $places, $initialPlaces, $transitions);
        if ($issues !== []) {
            throw new InvalidDefinition($name, $issues);
        }

        $indexedPlaces = [];
        foreach ($places as $place) {
            $indexedPlaces[$place->name] = $place;
        }

        $indexedTransitions = [];
        $outgoing = [];
        foreach ($transitions as $transition) {
            $indexedTransitions[$transition->name] = $transition;
            foreach ($transition->froms as $from) {
                $outgoing[$from][] = $transition;
            }
        }

        $this->places = $indexedPlaces;
        $this->transitions = $indexedTransitions;
        $this->outgoing = $outgoing;
    }

    public static function stateMachine(string $name): DefinitionBuilder
    {
        return new DefinitionBuilder($name, MachineType::StateMachine);
    }

    public static function workflow(string $name): DefinitionBuilder
    {
        return new DefinitionBuilder($name, MachineType::Workflow);
    }

    public function isStateMachine(): bool
    {
        return $this->type === MachineType::StateMachine;
    }

    /**
     * @return array<string, Place>
     */
    public function places(): array
    {
        return $this->places;
    }

    /**
     * @return list<string>
     */
    public function placeNames(): array
    {
        return array_keys($this->places);
    }

    public function hasPlace(string $name): bool
    {
        return isset($this->places[$name]);
    }

    /**
     * @throws UndefinedPlace
     */
    public function place(string $name): Place
    {
        return $this->places[$name] ?? throw new UndefinedPlace($this->name, $name);
    }

    public function isInitial(string $place): bool
    {
        return \in_array($place, $this->initialPlaces, true);
    }

    /**
     * @return list<string>
     */
    public function finalPlaces(): array
    {
        return array_keys(array_filter($this->places, static fn(Place $p): bool => $p->final));
    }

    /**
     * @return list<Transition>
     */
    public function transitions(): array
    {
        return array_values($this->transitions);
    }

    public function hasTransition(string $name): bool
    {
        return isset($this->transitions[$name]);
    }

    /**
     * @throws UndefinedTransition
     */
    public function transition(string $name): Transition
    {
        return $this->transitions[$name] ?? throw new UndefinedTransition($this->name, $name);
    }

    /**
     * Transitions that list the given place among their sources.
     *
     * @return list<Transition>
     */
    public function outgoing(string $place): array
    {
        return $this->outgoing[$place] ?? [];
    }

    public function initialMarking(): Marking
    {
        return new Marking(...$this->initialPlaces);
    }

    /**
     * Whether the marking structurally enables the transition, ignoring guards.
     *
     * A state machine fires from any one of its sources; a workflow needs all of them (a join).
     */
    public function isEnabledIn(Transition $transition, Marking $marking): bool
    {
        $marked = 0;
        foreach ($transition->froms as $from) {
            if ($marking->has($from)) {
                ++$marked;
            }
        }

        return $this->isStateMachine() ? $marked > 0 : $marked === \count($transition->froms);
    }

    /**
     * Pure firing rule: the marking that results from applying the transition.
     */
    public function fire(Transition $transition, Marking $marking): Marking
    {
        return $marking->without(...$transition->froms)->with(...$transition->tos);
    }
}
