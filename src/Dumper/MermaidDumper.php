<?php

declare(strict_types=1);

namespace Statecraft\Dumper;

use Statecraft\Definition\Definition;
use Statecraft\Definition\Transition;
use Statecraft\Marking\Marking;

/**
 * Renders a Mermaid stateDiagram-v2, which GitHub, GitLab and most wikis display inline.
 *
 * Workflow transitions with several inputs or outputs are drawn through Mermaid's
 * join and fork pseudo-states, the closest notation the diagram type offers.
 */
final class MermaidDumper implements Dumper
{
    public function __construct(private readonly string $direction = 'LR') {}

    public function dump(Definition $definition, ?Marking $highlight = null): string
    {
        $lines = ['---', 'title: ' . $definition->name, '---', 'stateDiagram-v2', '    direction ' . $this->direction];

        foreach ($definition->places() as $name => $place) {
            $id = $this->id($name);
            if ($id !== $name || $place->label !== null) {
                $lines[] = \sprintf('    state "%s" as %s', $this->text($place->label()), $id);
            }
        }

        foreach ($definition->initialPlaces as $initial) {
            $lines[] = '    [*] --> ' . $this->id($initial);
        }

        foreach ($definition->transitions() as $transition) {
            array_push($lines, ...$this->transition($transition, $definition->isStateMachine()));
        }

        foreach ($definition->finalPlaces() as $final) {
            $lines[] = '    ' . $this->id($final) . ' --> [*]';
        }

        if ($highlight !== null && !$highlight->isEmpty()) {
            $lines[] = '    classDef current fill:#fde68a,stroke:#b45309,stroke-width:2px';
            $lines[] = '    class ' . implode(',', array_map($this->id(...), $highlight->places())) . ' current';
        }

        return implode("\n", $lines) . "\n";
    }

    public function extension(): string
    {
        return 'mmd';
    }

    /**
     * @return list<string>
     */
    private function transition(Transition $transition, bool $isStateMachine): array
    {
        $label = $this->text($transition->name);
        $froms = array_map($this->id(...), $transition->froms);
        $tos = array_map($this->id(...), $transition->tos);

        // In a state machine several sources mean "from any of them", so each is its own edge.
        if ($isStateMachine || (\count($froms) === 1 && \count($tos) === 1)) {
            return array_map(static fn(string $from): string => \sprintf('    %s --> %s: %s', $from, $tos[0], $label), $froms);
        }

        $lines = [];
        $entry = $froms[0];
        $exit = $tos[0];

        if (\count($froms) > 1) {
            $entry = $this->id($transition->name) . '_join';
            $lines[] = \sprintf('    state %s <<join>>', $entry);
        }
        if (\count($tos) > 1) {
            $exit = $this->id($transition->name) . '_fork';
            $lines[] = \sprintf('    state %s <<fork>>', $exit);
        }
        if (\count($froms) > 1) {
            foreach ($froms as $from) {
                $lines[] = \sprintf('    %s --> %s', $from, $entry);
            }
        }

        $lines[] = \sprintf('    %s --> %s: %s', $entry, $exit, $label);

        if (\count($tos) > 1) {
            foreach ($tos as $to) {
                $lines[] = \sprintf('    %s --> %s', $exit, $to);
            }
        }

        return $lines;
    }

    private function id(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '_', $name);
    }

    private function text(string $text): string
    {
        return str_replace(['"', "\n"], ['#quot;', ' '], $text);
    }
}
