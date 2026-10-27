# Defining machines with the builder

The builder is the right choice when the definition comes from configuration, when guards are
closures, or when you prefer to keep the state enum free of behaviour.

```php
use Statecraft\Definition\Definition;

$definition = Definition::stateMachine('door')
    ->initial('closed')
    ->places('open', 'locked')
    ->final('broken')
    ->transition('open', from: 'closed', to: 'open')
    ->transition('close', from: 'open', to: 'closed')
    ->transition('lock', from: 'closed', to: 'locked', guard: fn (TransitionContext $c) => $c->actor !== null)
    ->transition('unlock', from: 'locked', to: 'closed')
    ->transition('break', from: ['closed', 'open', 'locked'], to: 'broken')
    ->build();
```

`Definition::workflow('name')` starts a [workflow](workflows.md) instead.

## Methods

| Method | Purpose |
|--------|---------|
| `initial(...$states)` | Declares the initial state(s), declaring the states themselves if needed. |
| `place($state, final, label, metadata)` | Declares one state with options. |
| `places(...$states)` | Declares several plain states. |
| `final(...$states)` | Declares final states. Final states must not have outgoing transitions. |
| `transition($name, $from, $to, $guard, $metadata)` | Declares a transition. `$from`/`$to` accept a string, an enum case or an iterable of those. |
| `metadata(array)` | Merges machine-level metadata. |
| `build()` | Validates and returns the immutable `Definition`, or throws `InvalidDefinition`. |

Every method accepts enum cases wherever a state is expected, so a pure data enum can be used
with the builder too.

## Validation

`build()` (and the `Definition` constructor itself) runs `DefinitionValidator`. All problems are
collected into one `InvalidDefinition` exception whose `issues` property is a list of
`ValidationIssue` objects with a machine-readable `IssueCode`:

| Code | Raised when |
|------|-------------|
| `no_places` | the machine declares no states |
| `duplicate_place` | a state is declared twice |
| `missing_initial_place` | no initial state |
| `unknown_initial_place` | the initial state is not declared |
| `multiple_initial_places` | a state machine declares more than one initial state |
| `duplicate_transition` | two transitions share a name |
| `empty_transition` | a transition has no source or no target |
| `unknown_place` | a transition refers to an undeclared state (usually a typo) |
| `multiple_targets` | a state machine transition has more than one target |
| `unreachable_place` | a state cannot be reached from the initial marking |
| `dead_end` | a non-final state has no outgoing transition |
| `final_place_has_exit` | a final state has an outgoing transition |

Graph checks (reachability, dead ends) only run once the structural checks pass, so the report
points at the cause rather than its consequences.

States are never created implicitly by `transition()`. That is deliberate: a misspelled state
name is reported as `unknown_place` instead of silently becoming a new dead-end state.
