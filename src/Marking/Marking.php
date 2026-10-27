<?php

declare(strict_types=1);

namespace Statecraft\Marking;

use Countable;
use Statecraft\Exception\InvalidMarking;

/**
 * The set of currently marked places. Immutable; operations return a new instance.
 *
 * A state machine marking always has exactly one place, a workflow marking can have many.
 * Places are kept sorted so that two markings with the same places compare equal.
 */
final readonly class Marking implements Countable
{
    /** @var list<string> */
    private array $places;

    public function __construct(string ...$places)
    {
        $unique = array_values(array_unique(array_values($places)));
        sort($unique);
        $this->places = $unique;
    }

    /**
     * @return list<string>
     */
    public function places(): array
    {
        return $this->places;
    }

    public function has(string $place): bool
    {
        return \in_array($place, $this->places, true);
    }

    public function isEmpty(): bool
    {
        return $this->places === [];
    }

    public function count(): int
    {
        return \count($this->places);
    }

    public function with(string ...$places): self
    {
        return new self(...$this->places, ...array_values($places));
    }

    public function without(string ...$places): self
    {
        return new self(...array_values(array_diff($this->places, $places)));
    }

    public function equals(self $other): bool
    {
        return $this->places === $other->places;
    }

    /**
     * The only marked place, for state machines.
     *
     * @throws InvalidMarking when the marking does not hold exactly one place
     */
    public function single(): string
    {
        if (\count($this->places) !== 1) {
            throw new InvalidMarking(\sprintf('Expected exactly one marked state, got %d [%s].', \count($this->places), $this->toString()));
        }

        return $this->places[0];
    }

    public function toString(): string
    {
        return implode(', ', $this->places);
    }
}
