<?php

declare(strict_types=1);

namespace Statecraft\Definition;

use BackedEnum;
use UnitEnum;

/**
 * Normalises the different ways a state can be referenced into its canonical string name.
 */
final class PlaceName
{
    private function __construct() {}

    public static function of(string|UnitEnum $place): string
    {
        return match (true) {
            \is_string($place) => $place,
            $place instanceof BackedEnum => (string) $place->value,
            default => $place->name,
        };
    }

    /**
     * @param string|UnitEnum|iterable<string|UnitEnum> $places
     *
     * @return list<string>
     */
    public static function list(string|UnitEnum|iterable $places): array
    {
        if (\is_string($places) || $places instanceof UnitEnum) {
            return [self::of($places)];
        }

        $names = [];
        foreach ($places as $place) {
            $names[] = self::of($place);
        }

        return array_values(array_unique($names));
    }
}
