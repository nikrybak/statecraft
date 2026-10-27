# Defining machines with attributes

Attributes keep the definition next to the type that names the states. The `AttributeLoader`
turns them into the same immutable `Definition` the builder produces, and caches it per class.

```php
use Statecraft\Attribute\State;
use Statecraft\Attribute\StateMachine;
use Statecraft\Attribute\Transition;

#[StateMachine(name: 'order')]
#[Transition('cancel', from: [self::Pending, self::Paid], to: self::Cancelled)]
enum OrderStatus: string
{
    #[State(initial: true)]
    #[Transition('pay', to: self::Paid, guard: PaymentCaptured::class)]
    case Pending = 'pending';

    #[Transition('ship', to: self::Shipped)]
    case Paid = 'paid';

    #[Transition('deliver', to: self::Delivered)]
    case Shipped = 'shipped';

    #[State(final: true)]
    case Delivered = 'delivered';

    #[State(final: true)]
    case Cancelled = 'cancelled';
}

$definition = (new AttributeLoader())->load(OrderStatus::class);
```

## Rules

| Attribute | Target | Meaning |
|-----------|--------|---------|
| `#[StateMachine(name, type, metadata)]` | enum or class | Marks the type as a machine. `name` defaults to the snake_cased short class name, `type` to `MachineType::StateMachine`. Required for CLI discovery. |
| `#[State(initial, final, label, metadata)]` | enum case or class constant | On an enum every case is a state, the attribute only adds flags. On a plain class only constants with the attribute are states. |
| `#[Transition(name, from, to, guard, metadata)]` | enum case, class constant or the type itself | Repeatable. On a state, `from` defaults to that state. On the type, `from` is required. |

- State names are the backing value of a backed enum case, the case name of a pure enum, or the constant's string value.
- `from` and `to` accept a single state or a list; enum cases and strings can be mixed.
- `guard` is a class name or a PSR-11 service id of a [Guard](guards.md). Closures cannot appear in attributes; use the builder for those.
- `metadata` arrays end up on `Place::$metadata`, `Transition::$metadata` and `Definition::$metadata`. The loader adds `class` to the definition metadata.

## Plain classes

When the states are not an enum, constants work the same way:

```php
#[StateMachine(type: MachineType::Workflow)]
#[Transition('publish', from: [self::SPELL_CHECKED, self::FACT_CHECKED], to: self::PUBLISHED)]
final class ArticleStates
{
    #[State(initial: true)]
    #[Transition('submit', to: [self::SPELLING, self::FACTS])]
    public const string DRAFT = 'draft';
    // ...
}
```

## Errors

Mapping problems throw `AttributeMappingError` (for example a class-level transition without
`from`, or a state constant that is an integer). Structural problems in the resulting graph
throw `InvalidDefinition`, exactly as with the builder.
