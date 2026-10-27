<?php

declare(strict_types=1);

namespace Statecraft\Marking;

use ReflectionException;
use ReflectionMethod;
use Statecraft\Definition\Definition;
use Statecraft\Exception\MarkingStoreError;

/**
 * Reads and writes the marking through a getter and a setter on the subject.
 *
 * The setter receives an enum case when its first parameter is typed with an enum,
 * a list of place names for workflows, and a plain string otherwise.
 */
final readonly class MethodMarkingStore implements MarkingStore
{
    public function __construct(
        private string $getter = 'getState',
        private string $setter = 'setState',
    ) {}

    public function read(Definition $definition, object $subject): Marking
    {
        return MarkingCodec::decode($definition, $this->method($subject, $this->getter)->invoke($subject));
    }

    public function write(Definition $definition, object $subject, Marking $marking): void
    {
        $setter = $this->method($subject, $this->setter);
        $parameter = $setter->getParameters()[0] ?? throw new MarkingStoreError(\sprintf('%s::%s() must accept the new state as its first argument.', $subject::class, $this->setter));

        $setter->invoke($subject, MarkingCodec::encode($definition, $marking, $parameter->getType()));
    }

    private function method(object $subject, string $name): ReflectionMethod
    {
        try {
            $method = new ReflectionMethod($subject, $name);
        } catch (ReflectionException $e) {
            throw new MarkingStoreError(\sprintf('Subject %s has no method %s().', $subject::class, $name), 0, $e);
        }
        if (!$method->isPublic()) {
            throw new MarkingStoreError(\sprintf('%s::%s() must be public to be used as a marking accessor.', $subject::class, $name));
        }

        return $method;
    }
}
