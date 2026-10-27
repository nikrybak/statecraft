# Guards

A guard decides whether an enabled transition may fire. It runs only after the marking check
passed, so it can assume the subject is in one of the transition's source states.

## Three ways to declare a guard

```php
// 1. A class implementing Guard, referenced by class name (attributes or builder)
final class PaymentCaptured implements Guard
{
    public function check(TransitionContext $context): GuardResult
    {
        return GuardResult::when($context->get('payment_id') !== null, 'a captured payment id is required');
    }
}

// 2. A closure returning bool or GuardResult (builder only)
->transition('approve', 'review', 'approved', guard: fn (TransitionContext $c): bool => $c->actor === 'editor')

// 3. A PSR-11 service id
#[Transition('refund', to: self::Refunded, guard: 'guards.refund_window')]
```

## What a guard sees

`TransitionContext` carries:

- `definition` - the machine definition
- `subject` - the object being transitioned
- `transition` - the `Transition` being attempted
- `marking` - the marking before the transition
- `actor` - the optional actor passed to `apply()` / `can()`
- `metadata` - the array passed to `apply()` / `can()`; read with `get('key', $default)`

## Resolution

`GuardResolver` turns a string reference into a `Guard`:

1. If a container was given and `has($id)`, the service is used. This is how guards get dependencies.
2. Otherwise, if the string is a class implementing `Guard` with a no-argument constructor, it is instantiated.
3. Otherwise `GuardResolutionFailed` is thrown, with a message that says which case applied.

Resolved guards are reused for the lifetime of the resolver.

```php
$machine = new Machine($definition, guards: new GuardResolver($container));
```

## Denials

`can()` returns `false` and `blockers()` returns a `TransitionBlocker` with code
`guard_failed` and the guard's reason. `apply()` dispatches `GuardFailed` and `TransitionBlocked`
and throws `TransitionNotAllowed`, whose message contains every reason.

## Keep guards free of side effects

Guards run for every `can()`, `blockers()` and `enabledTransitions()` call, which UIs tend to call
often. Anything that changes state belongs in a [before hook or a listener](events.md).
