<?php

declare(strict_types=1);

namespace Statecraft\Dumper;

use Statecraft\Definition\Definition;
use Statecraft\Definition\Place;
use Statecraft\Marking\Marking;

/**
 * Renders Graphviz DOT. State machines become a classic state chart; workflows are drawn
 * as a Petri net, with places as circles and transitions as boxes, so forks and joins
 * are explicit. Render with: dot -Tsvg machine.dot -o machine.svg
 */
final class GraphvizDumper implements Dumper
{
    public function dump(Definition $definition, ?Marking $highlight = null): string
    {
        $highlight ??= new Marking();
        $lines = [
            \sprintf('digraph %s {', $this->quote($definition->name)),
            '    rankdir=LR;',
            '    fontname="Helvetica";',
            '    node [fontname="Helvetica", fontsize=11];',
            '    edge [fontname="Helvetica", fontsize=10];',
            '    __start [shape=point, width=0.15, label=""];',
        ];

        $isStateMachine = $definition->isStateMachine();
        foreach ($definition->places() as $place) {
            $lines[] = \sprintf('    %s [%s];', $this->place($place->name), $this->placeAttributes($place, $isStateMachine, $highlight));
        }

        foreach ($definition->initialPlaces as $initial) {
            $lines[] = \sprintf('    __start -> %s;', $this->place($initial));
        }

        foreach ($definition->transitions() as $transition) {
            if ($isStateMachine) {
                foreach ($transition->froms as $from) {
                    $lines[] = \sprintf(
                        '    %s -> %s [label=%s];',
                        $this->place($from),
                        $this->place($transition->tos[0]),
                        $this->quote($transition->name),
                    );
                }
                continue;
            }

            $node = $this->quote('t:' . $transition->name);
            $lines[] = \sprintf('    %s [shape=box, style=filled, fillcolor="#e5e7eb", label=%s];', $node, $this->quote($transition->name));
            foreach ($transition->froms as $from) {
                $lines[] = \sprintf('    %s -> %s;', $this->place($from), $node);
            }
            foreach ($transition->tos as $to) {
                $lines[] = \sprintf('    %s -> %s;', $node, $this->place($to));
            }
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    public function extension(): string
    {
        return 'dot';
    }

    private function placeAttributes(Place $place, bool $isStateMachine, Marking $highlight): string
    {
        $attributes = [
            'shape=' . ($isStateMachine ? 'box' : 'circle'),
            'label=' . $this->quote($place->label()),
        ];
        $styles = $isStateMachine ? ['rounded'] : [];
        if ($place->final) {
            $attributes[] = 'peripheries=2';
        }
        if ($highlight->has($place->name)) {
            $styles[] = 'filled';
            $attributes[] = 'fillcolor="#fde68a"';
        }
        if ($styles !== []) {
            $attributes[] = 'style="' . implode(',', $styles) . '"';
        }

        return implode(', ', $attributes);
    }

    private function place(string $name): string
    {
        return $this->quote('p:' . $name);
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\"\\\n") . '"';
    }
}
