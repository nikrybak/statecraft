<?php

declare(strict_types=1);

namespace Statecraft\Attribute;

use ReflectionClass;
use ReflectionClassConstant;
use Statecraft\Definition\Definition;
use Statecraft\Definition\Place;
use Statecraft\Definition\PlaceName;
use Statecraft\Definition\Transition as TransitionDefinition;
use Statecraft\Exception\AttributeMappingError;
use Statecraft\Exception\InvalidDefinition;
use UnitEnum;

/**
 * Builds a Definition from #[StateMachine], #[State] and #[Transition] attributes.
 */
final class AttributeLoader
{
    /** @var array<class-string, Definition> */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public static function isMachine(string $class): bool
    {
        return (new ReflectionClass($class))->getAttributes(StateMachine::class) !== [];
    }

    /**
     * @param class-string $class
     *
     * @throws AttributeMappingError
     * @throws InvalidDefinition
     */
    public function load(string $class): Definition
    {
        return $this->cache[$class] ??= $this->build($class);
    }

    /**
     * @param class-string $class
     */
    private function build(string $class): Definition
    {
        $reflection = new ReflectionClass($class);
        $machine = ($reflection->getAttributes(StateMachine::class)[0] ?? null)?->newInstance() ?? new StateMachine();

        $places = [];
        $initial = [];
        $transitions = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            $state = ($constant->getAttributes(State::class)[0] ?? null)?->newInstance();
            if ($state === null && !$constant->isEnumCase()) {
                continue;
            }
            $state ??= new State();

            $name = $this->placeName($constant);
            $places[] = new Place($name, $state->final, $state->label, $state->metadata);
            if ($state->initial) {
                $initial[] = $name;
            }

            foreach ($constant->getAttributes(Transition::class) as $attribute) {
                $transitions[] = $this->transition($attribute->newInstance(), $name);
            }
        }

        foreach ($reflection->getAttributes(Transition::class) as $attribute) {
            $transition = $attribute->newInstance();
            if ($transition->from === null) {
                throw new AttributeMappingError(\sprintf(
                    'Transition "%s" on class %s needs "from"; only transitions placed on a state may omit it.',
                    $transition->name,
                    $class,
                ));
            }
            $transitions[] = $this->transition($transition, null);
        }

        return new Definition(
            $machine->name ?? $this->defaultName($reflection->getShortName()),
            $machine->type,
            $places,
            $initial,
            $transitions,
            [...$machine->metadata, 'class' => $class],
        );
    }

    private function transition(Transition $attribute, ?string $ownerPlace): TransitionDefinition
    {
        $from = $attribute->from === null && $ownerPlace !== null ? [$ownerPlace] : PlaceName::list($attribute->from ?? []);

        return new TransitionDefinition($attribute->name, $from, PlaceName::list($attribute->to), $attribute->guard, $attribute->metadata);
    }

    private function placeName(ReflectionClassConstant $constant): string
    {
        $value = $constant->getValue();
        if (\is_string($value) || $value instanceof UnitEnum) {
            return PlaceName::of($value);
        }

        throw new AttributeMappingError(\sprintf(
            'State constant %s::%s must be a string or an enum case, got %s.',
            $constant->getDeclaringClass()->getName(),
            $constant->getName(),
            get_debug_type($value),
        ));
    }

    private function defaultName(string $shortName): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
