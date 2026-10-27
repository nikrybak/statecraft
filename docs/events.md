# Hooks and PSR-14 events

There are two extension points around a transition: hooks registered on the `Machine`, and events
sent to any PSR-14 dispatcher. Hooks are for behaviour that belongs to the machine; events are
for the rest of the application (notifications, projections, framework integration).

## Order of an `apply()`

1. Marking check. If the transition is not enabled: `TransitionBlocked`, then `TransitionNotAllowed` is thrown.
2. Guard. If it denies: `GuardFailed`, `TransitionBlocked`, then `TransitionNotAllowed` is thrown.
3. `TransitionStarted` event.
4. `before` hooks (wildcard hooks first, then hooks for this transition).
5. The new marking is written through the marking store.
6. The audit entry is recorded.
7. `after` hooks (wildcard first).
8. `TransitionCompleted` event.

An exception from steps 3 or 4 leaves the subject unchanged and nothing is recorded, so a before
hook is the place to call a payment gateway and abort on failure. Steps 5 to 8 are not
transactional by themselves: when the subject and the audit table live in the same database,
wrap `apply()` and your flush in one database transaction.

`can()`, `blockers()` and `enabledTransitions()` never dispatch events and never run hooks.

## Hooks

```php
$machine
    ->before('pay', function (TransitionContext $context): void {
        $this->gateway->capture($context->get('payment_id'));
    })
    ->after(Machine::ANY, function (TransitionContext $context, Marking $after): void {
        $this->logger->info('{t}: {to}', ['t' => $context->transition->name, 'to' => $after->toString()]);
    });
```

Registering a hook for a transition that does not exist throws `UndefinedTransition` immediately,
so a renamed transition cannot leave a hook silently orphaned.

## Events

All events extend `Statecraft\Event\TransitionEvent` and expose the `TransitionContext` plus
`machine()`, `subject()`, `transition()` and `markingBefore()`.

| Event | Extra data | Dispatched |
|-------|------------|------------|
| `TransitionStarted` | - | guards passed, before the marking changes |
| `TransitionCompleted` | `markingAfter` | after the marking is written and recorded |
| `TransitionBlocked` | `blockers` | right before `TransitionNotAllowed` is thrown |
| `GuardFailed` | `result`, `reason()` | when a guard denies during `apply()` |

Any dispatcher works:

```php
$machine = new Machine($definition, events: $symfonyDispatcher);
```

With Symfony, listen with `#[AsEventListener(event: TransitionCompleted::class)]` and filter on
`$event->machine()` and `$event->transition()->name`.
