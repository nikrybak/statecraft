<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /** @var list<string> */
    public array $log = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        $this->log[] = 'event:' . (new \ReflectionClass($event))->getShortName();

        return $event;
    }
}
