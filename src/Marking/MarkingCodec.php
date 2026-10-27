<?php

declare(strict_types=1);

namespace Statecraft\Marking;

use ReflectionNamedType;
use ReflectionType;
use Statecraft\Definition\Definition;
use Statecraft\Definition\PlaceName;
use Statecraft\Exception\InvalidMarking;
use UnitEnum;

/**
 * Converts between the raw value a subject holds and a Marking.
 *
 * Supported raw values: null (no state yet), string, enum case, and for workflows a list
 * of those or a map of place => bool (the shape commonly stored in a JSON column).
 *
 * @internal
 */
final class MarkingCodec
{
    public static function decode(Definition $definition, mixed $raw): Marking
    {
        $places = match (true) {
            $raw === null => [],
            \is_string($raw), $raw instanceof UnitEnum => [PlaceName::of($raw)],
            \is_array($raw) => self::decodeArray($raw),
            default => throw new InvalidMarking(\sprintf('Machine "%s" cannot read a marking from a value of type %s.', $definition->name, get_debug_type($raw))),
        };

        foreach ($places as $place) {
            if (!$definition->hasPlace($place)) {
                throw new InvalidMarking(\sprintf('Subject is in state "%s", which machine "%s" does not define.', $place, $definition->name));
            }
        }

        if ($definition->isStateMachine() && \count($places) > 1) {
            throw new InvalidMarking(\sprintf('State machine "%s" read %d marked states from the subject; expected one.', $definition->name, \count($places)));
        }

        return new Marking(...$places);
    }

    /**
     * @return string|UnitEnum|list<string>
     */
    public static function encode(Definition $definition, Marking $marking, ?ReflectionType $target): string|UnitEnum|array
    {
        if (!$definition->isStateMachine()) {
            return $marking->places();
        }

        $place = $marking->single();
        $enumClass = $target instanceof ReflectionNamedType && enum_exists($target->getName()) ? $target->getName() : null;
        if ($enumClass === null) {
            return $place;
        }

        return self::toEnum($enumClass, $place);
    }

    /**
     * @param class-string<UnitEnum> $enumClass
     */
    private static function toEnum(string $enumClass, string $place): UnitEnum
    {
        foreach ($enumClass::cases() as $case) {
            if (PlaceName::of($case) === $place) {
                return $case;
            }
        }

        throw new InvalidMarking(\sprintf('State "%s" has no matching case in enum %s.', $place, $enumClass));
    }

    /**
     * @param array<array-key, mixed> $raw
     *
     * @return list<string>
     */
    private static function decodeArray(array $raw): array
    {
        $places = [];
        foreach ($raw as $key => $value) {
            if (\is_string($key)) {
                if ($value === true || $value === 1) {
                    $places[] = $key;
                }
                continue;
            }
            if (!\is_string($value) && !$value instanceof UnitEnum) {
                throw new InvalidMarking(\sprintf('Marking list items must be strings or enum cases, got %s.', get_debug_type($value)));
            }
            $places[] = PlaceName::of($value);
        }

        return $places;
    }
}
