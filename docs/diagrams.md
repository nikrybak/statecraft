# Diagrams and the CLI

## Dumpers

```php
echo (new MermaidDumper())->dump($definition);
echo (new GraphvizDumper())->dump($definition, $machine->marking($order)); // highlight current state
```

- `MermaidDumper` writes a `stateDiagram-v2`, rendered natively by GitHub, GitLab, Notion and
  most wikis. Workflow forks and joins use Mermaid's `<<fork>>` and `<<join>>` pseudo-states.
  The constructor takes the layout direction (`LR` by default).
- `GraphvizDumper` writes DOT. State machines are drawn as rounded boxes; workflows as a Petri
  net with circles for places and boxes for transitions. Render with
  `dot -Tsvg order.dot -o order.svg`.

Initial states get an arrow from a start node, final states a double border (DOT) or an arrow
to `[*]` (Mermaid). Highlighted places are filled.

## `bin/statecraft`

The CLI has no dependencies beyond the library. It scans a folder for classes and enums carrying
`#[StateMachine]`, loads them through the Composer autoloader (or includes the file when the class
is not autoloadable), and validates or renders them.

```text
vendor/bin/statecraft validate src/Domain
vendor/bin/statecraft validate src --namespace='App\Billing'
vendor/bin/statecraft render src/Domain --format=dot --output=docs/diagrams
vendor/bin/statecraft render src/Domain --machine=order
```

Example output of `validate`:

```text
OK    order (App\Domain\OrderStatus): 6 states, 5 transitions, state machine
FAIL  App\Domain\TicketStatus
      Machine "ticket" has 2 definition error(s):
        - State "orphan" cannot be reached from the initial state.
        - State "stuck" has no outgoing transitions and is not marked final (dead end).

2 machine(s) checked, 1 failed.
```

Exit codes: `0` everything valid, `1` an invalid machine or nothing found, `2` a usage error.
That makes `statecraft validate src` a one-line CI check that no definition is broken.

`--bootstrap=path/to/autoload.php` includes a file before scanning, for projects whose autoloader
is not the one next to the binary.
