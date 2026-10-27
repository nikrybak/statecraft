<?php

declare(strict_types=1);

namespace Statecraft\Definition;

/**
 * Structural checks run on every definition before it can be used.
 *
 * All problems are collected rather than failing on the first one, so a single run of
 * the CLI or a single exception message shows everything that has to be fixed.
 */
final class DefinitionValidator
{
    /**
     * @param list<Place>      $places
     * @param list<string>     $initialPlaces
     * @param list<Transition> $transitions
     *
     * @return list<ValidationIssue>
     */
    public function validate(MachineType $type, array $places, array $initialPlaces, array $transitions): array
    {
        if ($places === []) {
            return [new ValidationIssue(IssueCode::NoPlaces, 'The machine declares no states.')];
        }

        $issues = [];
        $known = [];
        foreach ($places as $place) {
            if (isset($known[$place->name])) {
                $issues[] = new ValidationIssue(IssueCode::DuplicatePlace, \sprintf('State "%s" is declared more than once.', $place->name), $place->name);
            }
            $known[$place->name] = $place;
        }

        $issues = [...$issues, ...$this->checkInitial($type, $known, $initialPlaces)];
        $issues = [...$issues, ...$this->checkTransitions($type, $known, $transitions)];

        // Graph checks on a broken graph produce noise that hides the real cause.
        if ($issues !== []) {
            return $issues;
        }

        return [...$this->checkReachability($type, $known, $initialPlaces, $transitions), ...$this->checkExits($known, $transitions)];
    }

    /**
     * @param array<string, Place> $known
     * @param list<string>         $initialPlaces
     *
     * @return list<ValidationIssue>
     */
    private function checkInitial(MachineType $type, array $known, array $initialPlaces): array
    {
        if ($initialPlaces === []) {
            return [new ValidationIssue(IssueCode::MissingInitialPlace, 'No initial state is declared.')];
        }

        $issues = [];
        if ($type === MachineType::StateMachine && \count($initialPlaces) > 1) {
            $issues[] = new ValidationIssue(
                IssueCode::MultipleInitialPlaces,
                \sprintf('A state machine has exactly one initial state, got: %s.', implode(', ', $initialPlaces)),
            );
        }

        foreach ($initialPlaces as $initial) {
            if (!isset($known[$initial])) {
                $issues[] = new ValidationIssue(IssueCode::UnknownInitialPlace, \sprintf('Initial state "%s" is not declared.', $initial), $initial);
            }
        }

        return $issues;
    }

    /**
     * @param array<string, Place> $known
     * @param list<Transition>     $transitions
     *
     * @return list<ValidationIssue>
     */
    private function checkTransitions(MachineType $type, array $known, array $transitions): array
    {
        $issues = [];
        $seen = [];
        foreach ($transitions as $transition) {
            $name = $transition->name;
            if (isset($seen[$name])) {
                $issues[] = new ValidationIssue(IssueCode::DuplicateTransition, \sprintf('Transition "%s" is declared more than once.', $name), $name);
            }
            $seen[$name] = true;

            if ($transition->froms === [] || $transition->tos === []) {
                $issues[] = new ValidationIssue(IssueCode::EmptyTransition, \sprintf('Transition "%s" needs at least one source and one target state.', $name), $name);
            }

            if ($type === MachineType::StateMachine && \count($transition->tos) > 1) {
                $issues[] = new ValidationIssue(
                    IssueCode::MultipleTargets,
                    \sprintf('Transition "%s" has %d targets; a state machine transition has exactly one (use a workflow for forks).', $name, \count($transition->tos)),
                    $name,
                );
            }

            foreach ([...$transition->froms, ...$transition->tos] as $place) {
                if (!isset($known[$place])) {
                    $issues[] = new ValidationIssue(IssueCode::UnknownPlace, \sprintf('Transition "%s" refers to undeclared state "%s".', $name, $place), $name);
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<string, Place> $known
     * @param list<string>         $initialPlaces
     * @param list<Transition>     $transitions
     *
     * @return list<ValidationIssue>
     */
    private function checkReachability(MachineType $type, array $known, array $initialPlaces, array $transitions): array
    {
        $reached = array_fill_keys($initialPlaces, true);

        // For workflows this is an over-approximation: a join counts as enabled once every input
        // place is reachable on its own, without proving they can be marked at the same time.
        do {
            $changed = false;
            foreach ($transitions as $transition) {
                $reachedFroms = \count(array_intersect_key(array_flip($transition->froms), $reached));
                $enabled = $type === MachineType::StateMachine
                    ? $reachedFroms > 0
                    : $reachedFroms === \count($transition->froms);

                if (!$enabled) {
                    continue;
                }
                foreach ($transition->tos as $to) {
                    if (!isset($reached[$to])) {
                        $reached[$to] = true;
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        $issues = [];
        foreach (array_keys($known) as $name) {
            if (!isset($reached[$name])) {
                $issues[] = new ValidationIssue(IssueCode::UnreachablePlace, \sprintf('State "%s" cannot be reached from the initial state.', $name), $name);
            }
        }

        return $issues;
    }

    /**
     * @param array<string, Place> $known
     * @param list<Transition>     $transitions
     *
     * @return list<ValidationIssue>
     */
    private function checkExits(array $known, array $transitions): array
    {
        $exits = [];
        foreach ($transitions as $transition) {
            foreach ($transition->froms as $from) {
                $exits[$from][] = $transition->name;
            }
        }

        $issues = [];
        foreach ($known as $name => $place) {
            if ($place->final && isset($exits[$name])) {
                $issues[] = new ValidationIssue(
                    IssueCode::FinalPlaceHasExit,
                    \sprintf('Final state "%s" has outgoing transition(s): %s.', $name, implode(', ', $exits[$name])),
                    $name,
                );
            }
            if (!$place->final && !isset($exits[$name])) {
                $issues[] = new ValidationIssue(
                    IssueCode::DeadEnd,
                    \sprintf('State "%s" has no outgoing transitions and is not marked final (dead end).', $name),
                    $name,
                );
            }
        }

        return $issues;
    }
}
