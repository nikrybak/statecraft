<?php

declare(strict_types=1);

namespace Statecraft\Tests\Property;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Statecraft\Audit\InMemoryAuditTrail;
use Statecraft\Definition\Definition;
use Statecraft\Definition\DefinitionBuilder;
use Statecraft\Definition\Transition;
use Statecraft\Dumper\GraphvizDumper;
use Statecraft\Dumper\MermaidDumper;
use Statecraft\Exception\TransitionNotAllowed;
use Statecraft\Machine;
use Statecraft\Tests\Fixtures\Machines;

/**
 * Property-style tests: random walks over definitions (fixed and randomly generated),
 * checked against an independent model of the firing rule.
 *
 * Seeds are deterministic so a failure can be reproduced; the seed is part of every message.
 */
#[CoversNothing]
final class RandomTransitionSequenceTest extends TestCase
{
    private const int STEPS = 60;

    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach (range(1, 40) as $seed) {
            yield 'seed ' . $seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testRandomWalkOnFixedMachinesMatchesModel(int $seed): void
    {
        $random = new Randomizer(new Mt19937($seed));

        foreach ([Machines::door(), Machines::approval()] as $definition) {
            $this->walk($definition, $random, $seed);
        }
    }

    #[DataProvider('seeds')]
    public function testRandomlyGeneratedStateMachinesAreValidAndBehave(int $seed): void
    {
        $random = new Randomizer(new Mt19937($seed));
        $definition = $this->generateStateMachine($random, $seed);

        $mermaid = (new MermaidDumper())->dump($definition);
        $dot = (new GraphvizDumper())->dump($definition);
        foreach ($definition->transitions() as $transition) {
            self::assertStringContainsString(': ' . $transition->name . "\n", $mermaid, "seed {$seed}");
            self::assertStringContainsString('[label="' . $transition->name . '"]', $dot, "seed {$seed}");
        }

        $this->walk($definition, $random, $seed);
    }

    private function walk(Definition $definition, Randomizer $random, int $seed): void
    {
        $audit = new InMemoryAuditTrail();
        $machine = new Machine($definition, audit: $audit);
        $subject = new class {
            public string $id = 'subject';

            /** @var string|list<string>|null */
            public string|array|null $state = null;
        };

        $model = $definition->initialPlaces;
        $applied = 0;
        $transitions = $definition->transitions();

        for ($step = 0; $step < self::STEPS; ++$step) {
            $transition = $transitions[$random->getInt(0, \count($transitions) - 1)];
            $expectedEnabled = $this->modelEnabled($definition, $transition, $model);
            $where = \sprintf('seed %d, step %d, machine %s, transition %s, model [%s]', $seed, $step, $definition->name, $transition->name, implode(',', $model));

            self::assertSame($expectedEnabled, $machine->can($subject, $transition->name), $where);

            $enabledNames = array_map(static fn(Transition $t): string => $t->name, $machine->enabledTransitions($subject));
            self::assertSame($expectedEnabled, \in_array($transition->name, $enabledNames, true), $where);

            try {
                $marking = $machine->apply($subject, $transition->name);
                self::assertTrue($expectedEnabled, $where . ': apply succeeded although the model says disabled');
                $model = $this->modelFire($transition, $model);
                ++$applied;
                self::assertSame($model, $marking->places(), $where);
            } catch (TransitionNotAllowed) {
                self::assertFalse($expectedEnabled, $where . ': apply failed although the model says enabled');
            }

            $current = $machine->marking($subject);
            self::assertSame($model, $current->places(), $where);
            foreach ($current->places() as $place) {
                self::assertTrue($definition->hasPlace($place), $where);
            }
            if ($definition->isStateMachine()) {
                self::assertCount(1, $current, $where);
            }
            if ($machine->isFinished($subject)) {
                self::assertSame([], $machine->enabledTransitions($subject), $where . ': a finished subject has no way out');
            }
        }

        self::assertCount($applied, $audit->all(), "seed {$seed}: one audit entry per applied transition");
        $previous = $definition->initialPlaces;
        foreach ($audit->all() as $entry) {
            self::assertSame($this->sorted($previous), $entry->from, "seed {$seed}: audit chain is contiguous");
            $previous = $entry->to;
        }
    }

    /**
     * @param list<string> $model
     */
    private function modelEnabled(Definition $definition, Transition $transition, array $model): bool
    {
        $present = array_intersect($transition->froms, $model);

        return $definition->isStateMachine() ? $present !== [] : \count($present) === \count($transition->froms);
    }

    /**
     * @param list<string> $model
     *
     * @return list<string>
     */
    private function modelFire(Transition $transition, array $model): array
    {
        return $this->sorted([...array_diff($model, $transition->froms), ...$transition->tos]);
    }

    /**
     * @param array<string> $places
     *
     * @return list<string>
     */
    private function sorted(array $places): array
    {
        $places = array_values(array_unique($places));
        sort($places);

        return $places;
    }

    /**
     * A chain s0 -> s1 -> ... guarantees reachability; random extra edges add cycles and
     * shortcuts; the last state is final so there are no dead ends.
     */
    private function generateStateMachine(Randomizer $random, int $seed): Definition
    {
        $count = $random->getInt(2, 9);
        $builder = new DefinitionBuilder('generated_' . $seed);
        $builder->initial('s0');
        for ($i = 1; $i < $count - 1; ++$i) {
            $builder->place('s' . $i);
        }
        $builder->final('s' . ($count - 1));

        for ($i = 0; $i < $count - 1; ++$i) {
            $builder->transition('step_' . $i, 's' . $i, 's' . ($i + 1));
        }
        $extra = $random->getInt(0, $count * 2);
        for ($j = 0; $j < $extra; ++$j) {
            $from = $random->getInt(0, $count - 2);
            $to = $random->getInt(0, $count - 1);
            $builder->transition('jump_' . $j, 's' . $from, 's' . $to);
        }

        return $builder->build();
    }
}
