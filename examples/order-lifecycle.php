<?php

declare(strict_types=1);

use Statecraft\Attribute\AttributeLoader;
use Statecraft\Audit\InMemoryAuditTrail;
use Statecraft\Dumper\MermaidDumper;
use Statecraft\Examples\OrderLifecycle\Order;
use Statecraft\Examples\OrderLifecycle\OrderStatus;
use Statecraft\Exception\TransitionNotAllowed;
use Statecraft\Machine;
use Statecraft\Marking\PropertyMarkingStore;
use Statecraft\TransitionContext;

require __DIR__ . '/../vendor/autoload.php';

$audit = new InMemoryAuditTrail();
$machine = new Machine(
    (new AttributeLoader())->load(OrderStatus::class),
    new PropertyMarkingStore('status'),
    audit: $audit,
);

$machine->after('pay', static function (TransitionContext $context): void {
    $order = $context->subject;
    $paymentId = $context->get('payment_id');
    if ($order instanceof Order && \is_string($paymentId)) {
        $order->paymentId = $paymentId;
    }
});

$order = new Order('A-1001', 4_990);

try {
    $machine->apply($order, 'pay');
} catch (TransitionNotAllowed $e) {
    echo $e->getMessage(), "\n";
}

$machine->apply($order, 'pay', ['payment_id' => 'pi_123'], actor: 'checkout');
$machine->apply($order, 'ship', actor: 'warehouse');

echo 'Status: ', $order->status()->value, "\n";
echo 'Payment: ', $order->paymentId, "\n";
echo 'Next: ', implode(', ', array_map(static fn($t): string => $t->name, $machine->enabledTransitions($order))), "\n\n";

foreach ($audit->history('order', 'A-1001') as $entry) {
    printf("%s  %-8s %s -> %s by %s\n", $entry->occurredAt->format('H:i:s'), $entry->transition, implode(',', $entry->from), implode(',', $entry->to), $entry->actor ?? '-');
}

echo "\n", (new MermaidDumper())->dump($machine->definition(), $machine->marking($order));
