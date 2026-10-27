<?php

declare(strict_types=1);

namespace Statecraft\Marking;

use ReflectionException;
use ReflectionProperty;
use Statecraft\Definition\Definition;
use Statecraft\Exception\MarkingStoreError;
use Throwable;

/**
 * Keeps the marking in a property of the subject, public or not.
 *
 * Reflection is used instead of public access so domain objects can keep the state property
 * private and expose intention-revealing methods instead of a setter. When the property is
 * typed with an enum, the enum case is written back instead of a string.
 */
final class PropertyMarkingStore implements MarkingStore
{
    /** @var array<class-string, ReflectionProperty> */
    private array $reflections = [];

    public function __construct(private readonly string $property = 'state') {}

    public function read(Definition $definition, object $subject): Marking
    {
        $property = $this->reflect($subject);

        if (!$property->isInitialized($subject)) {
            return new Marking();
        }

        return MarkingCodec::decode($definition, $property->getValue($subject));
    }

    public function write(Definition $definition, object $subject, Marking $marking): void
    {
        $property = $this->reflect($subject);
        $value = MarkingCodec::encode($definition, $marking, $property->getType());

        try {
            $property->setValue($subject, $value);
        } catch (Throwable $e) {
            throw new MarkingStoreError(\sprintf('Cannot write state to %s::$%s: %s', $subject::class, $this->property, $e->getMessage()), 0, $e);
        }
    }

    private function reflect(object $subject): ReflectionProperty
    {
        try {
            return $this->reflections[$subject::class] ??= new ReflectionProperty($subject, $this->property);
        } catch (ReflectionException $e) {
            throw new MarkingStoreError(\sprintf('Subject %s has no property "%s" to hold its state.', $subject::class, $this->property), 0, $e);
        }
    }
}
