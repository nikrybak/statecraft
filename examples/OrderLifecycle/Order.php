<?php

declare(strict_types=1);

namespace Statecraft\Examples\OrderLifecycle;

final class Order
{
    private OrderStatus $status = OrderStatus::Pending;

    public ?string $paymentId = null;

    public function __construct(
        public readonly string $id,
        public readonly int $totalCents,
    ) {}

    public function status(): OrderStatus
    {
        return $this->status;
    }
}
