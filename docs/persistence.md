# Marking stores and the audit trail

## Marking stores

The machine never touches the subject directly; it reads and writes the marking through a
`MarkingStore`.

### `PropertyMarkingStore` (default)

```php
new Machine($definition, new PropertyMarkingStore('status'));
```

- Works with public, protected and private properties, so the entity does not need a setter.
- An uninitialised typed property or `null` means "no state yet"; `marking()` then returns the initial marking without writing it. Call `initialize($subject)` to persist it.
- If the property is typed with an enum, the matching case is written (backed or pure, int or string backed). Otherwise a string is written for state machines and a list of strings for workflows.

### `MethodMarkingStore`

```php
new Machine($definition, new MethodMarkingStore('getStatus', 'setStatus'));
```

The setter receives an enum case when its first parameter is typed with an enum.

### Your own store

Implement `MarkingStore::read()` and `write()` when the state lives elsewhere (a separate table,
a document store). `MarkingCodec` is internal but its rules are documented above; reuse them by
returning a `Marking` and accepting one.

## Audit trail

Pass an `AuditTrail` to record every applied transition:

```php
$machine = new Machine($definition, audit: new PdoAuditTrail($pdo), clock: $clock);
$machine->apply($order, 'pay', ['payment_id' => 'pi_123'], actor: 'user:42');

foreach ($trail->history('order', $order->getId()) as $entry) {
    // $entry->transition, ->from, ->to, ->actor, ->occurredAt, ->metadata
}
```

| Implementation | Use |
|----------------|-----|
| `InMemoryAuditTrail` | tests, scripts; `all()` returns every entry |
| `PdoAuditTrail` | MySQL, PostgreSQL, SQLite through PDO |

The subject id comes from a `SubjectIdResolver`. The default uses `getId()` or a public `$id`
property and throws when neither exists, since an object hash would be meaningless once the
request ends. Supply your own resolver for composite keys.

Time comes from `Statecraft\Audit\Clock` (same shape as PSR-20, so wrapping a PSR-20 clock is
one line). `SystemClock` returns UTC.

### Database schema

The DDL ships in `resources/migrations/` for `mysql`, `postgresql` and `sqlite`:

```php
Migrations::path('pgsql');  // absolute path to copy into your migration tool
Migrations::apply($pdo);    // runs the script matching the connection driver
```

Columns: `id`, `machine`, `subject_id`, `transition_name`, `from_places` (JSON), `to_places`
(JSON), `actor`, `metadata` (JSON), `occurred_at` (UTC, microseconds), with an index on
`(machine, subject_id, id)` for history queries. A custom table name, optionally schema
qualified, is the second constructor argument of `PdoAuditTrail`; it is validated against a
strict identifier pattern because it cannot be bound as a parameter.

Errors from PDO and JSON encoding are wrapped in `AuditTrailError`.
