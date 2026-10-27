<?php

declare(strict_types=1);

namespace Statecraft\Definition;

use Closure;
use Statecraft\Exception\InvalidDefinition;
use Statecraft\Guard\Guard;
use Statecraft\Guard\GuardResult;
use Statecraft\TransitionContext;
use UnitEnum;

/**
 * Fluent, mutable collector that produces an immutable {@see Definition}.
 *
 * States must be declared explicitly; a transition that mentions an undeclared state is
 * reported by validation instead of silently creating it, which catches typos.
 */
final class DefinitionBuilder
{
    /** @var list<Place> */
    private array $places = [];

    /** @var list<string> */
    private array $initial = [];

    /** @var list<Transition> */
    private array $transitions = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        private readonly string $name,
        private readonly MachineType $type = MachineType::StateMachine,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function place(string|UnitEnum $place, bool $final = false, ?string $label = null, array $metadata = []): self
    {
        $this->places[] = new Place(PlaceName::of($place), $final, $label, $metadata);

        return $this;
    }

    public function places(string|UnitEnum ...$places): self
    {
        foreach ($places as $place) {
            $this->place($place);
        }

        return $this;
    }

    /**
     * Declares the initial state(s) and the states themselves when not declared yet.
     * Workflows may start with several places marked.
     */
    public function initial(string|UnitEnum ...$places): self
    {
        foreach ($places as $place) {
            $name = PlaceName::of($place);
            $this->initial[] = $name;
            if (!$this->declares($name)) {
                $this->place($name);
            }
        }

        return $this;
    }

    public function final(string|UnitEnum ...$places): self
    {
        foreach ($places as $place) {
            $this->place($place, final: true);
        }

        return $this;
    }

    /**
     * @param string|UnitEnum|iterable<string|UnitEnum>            $from
     * @param string|UnitEnum|iterable<string|UnitEnum>            $to
     * @param Guard|(Closure(TransitionContext): (bool|GuardResult))|string|null $guard
     * @param array<string, mixed>                                 $metadata
     */
    public function transition(
        string $name,
        string|UnitEnum|iterable $from,
        string|UnitEnum|iterable $to,
        Guard|Closure|string|null $guard = null,
        array $metadata = [],
    ): self {
        $this->transitions[] = new Transition($name, PlaceName::list($from), PlaceName::list($to), $guard, $metadata);

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = [...$this->metadata, ...$metadata];

        return $this;
    }

    /**
     * @throws InvalidDefinition
     */
    public function build(): Definition
    {
        return new Definition($this->name, $this->type, $this->places, $this->initial, $this->transitions, $this->metadata);
    }

    private function declares(string $name): bool
    {
        foreach ($this->places as $place) {
            if ($place->name === $name) {
                return true;
            }
        }

        return false;
    }
}
