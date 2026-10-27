<?php

declare(strict_types=1);

namespace Statecraft\Tests\Fixtures;

final class Order
{
    public ?OrderStatus $status = null;

    public function __construct(
        public string $id = 'order-1',
        public int $amount = 100,
    ) {}
}
