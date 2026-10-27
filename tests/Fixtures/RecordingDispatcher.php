<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use OutOfBoundsException;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /** @var list<string> */
    public array $log = [];

    public function at(int $index): object
    {
        return $this->events[$index] ?? throw new OutOfBoundsException("No event at {$index}");
    }

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        $this->log[] = 'event:' . (new ReflectionClass($event))->getShortName();

        return $event;
    }
}
