<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Dumper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statecraft\Attribute\AttributeLoader;
use Statecraft\Definition\Definition;
use Statecraft\Dumper\GraphvizDumper;
use Statecraft\Dumper\MermaidDumper;
use Statecraft\Marking\Marking;
use Statecraft\Tests\Fixtures\Machines;
use Statecraft\Tests\Fixtures\OrderStatus;

#[CoversClass(MermaidDumper::class)]
#[CoversClass(GraphvizDumper::class)]
final class DumperTest extends TestCase
{
    public function testMermaidStateMachine(): void
    {
        $definition = (new AttributeLoader())->load(OrderStatus::class);

        self::assertSame(<<<'MERMAID'
            ---
            title: order
            ---
            stateDiagram-v2
                direction LR
                state "New order" as new
                [*] --> new
                new --> paid: pay
                paid --> shipped: ship
                shipped --> delivered: deliver
                new --> cancelled: cancel
                paid --> cancelled: cancel
                delivered --> [*]
                cancelled --> [*]
                classDef current fill:#fde68a,stroke:#b45309,stroke-width:2px
                class paid current

            MERMAID, (new MermaidDumper())->dump($definition, new Marking('paid')));
        self::assertSame('mmd', (new MermaidDumper())->extension());
    }

    public function testMermaidWorkflowUsesForkAndJoin(): void
    {
        $output = (new MermaidDumper('TB'))->dump(Machines::approval());

        self::assertStringContainsString("    direction TB\n", $output);
        self::assertStringContainsString("    state submit_fork <<fork>>\n    draft --> submit_fork: submit\n    submit_fork --> legal_review\n    submit_fork --> tech_review\n", $output);
        self::assertStringContainsString("    state approve_join <<join>>\n    legal_ok --> approve_join\n    tech_ok --> approve_join\n    approve_join --> approved: approve\n", $output);
        self::assertStringNotContainsString('classDef', $output);
    }

    public function testMermaidHandlesForkJoinAndUnsafeNames(): void
    {
        $definition = Definition::workflow('odd names')
            ->initial('in-a', 'in b')
            ->final('out"1', 'out.2')
            ->transition('swap all', ['in-a', 'in b'], ['out"1', 'out.2'])
            ->build();

        $output = (new MermaidDumper())->dump($definition);

        self::assertStringContainsString('    state "in-a" as in_a', $output);
        self::assertStringContainsString('    state "out#quot;1" as out_1', $output);
        self::assertStringContainsString("    state swap_all_join <<join>>\n    state swap_all_fork <<fork>>\n", $output);
        self::assertStringContainsString('    swap_all_join --> swap_all_fork: swap all', $output);
        self::assertStringContainsString('    [*] --> in_b', $output);
    }

    public function testGraphvizStateMachine(): void
    {
        $output = (new GraphvizDumper())->dump(Machines::door(), new Marking('open'));

        self::assertStringStartsWith("digraph \"door\" {\n    rankdir=LR;\n", $output);
        self::assertStringContainsString('    "p:open" [shape=box, label="open", fillcolor="#fde68a", style="rounded,filled"];', $output);
        self::assertStringContainsString('    "p:closed" [shape=box, label="closed", style="rounded"];', $output);
        self::assertStringContainsString('    "p:broken" [shape=box, label="broken", peripheries=2, style="rounded"];', $output);
        self::assertStringContainsString('    __start -> "p:closed";', $output);
        self::assertStringContainsString('    "p:locked" -> "p:broken" [label="break"];', $output);
        self::assertStringEndsWith("}\n", $output);
        self::assertSame('dot', (new GraphvizDumper())->extension());
    }

    public function testGraphvizWorkflowIsAPetriNet(): void
    {
        $output = (new GraphvizDumper())->dump(Machines::approval(), new Marking('legal_review', 'tech_review'));

        self::assertStringContainsString('    "p:draft" [shape=circle, label="draft"];', $output);
        self::assertStringContainsString('    "p:legal_review" [shape=circle, label="legal_review", fillcolor="#fde68a", style="filled"];', $output);
        self::assertStringContainsString('    "p:approved" [shape=circle, label="approved", peripheries=2];', $output);
        self::assertStringContainsString('    "t:submit" [shape=box, style=filled, fillcolor="#e5e7eb", label="submit"];', $output);
        self::assertStringContainsString("    \"p:legal_ok\" -> \"t:approve\";\n    \"p:tech_ok\" -> \"t:approve\";\n    \"t:approve\" -> \"p:approved\";", $output);
    }

    public function testGraphvizEscapesQuotes(): void
    {
        $definition = Definition::stateMachine('say "hi"')->initial('a')->final('b')->transition('go "now"', 'a', 'b')->build();

        $output = (new GraphvizDumper())->dump($definition);

        self::assertStringContainsString('digraph "say \"hi\"" {', $output);
        self::assertStringContainsString('[label="go \"now\""]', $output);
    }
}
