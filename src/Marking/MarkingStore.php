<?php

declare(strict_types=1);

namespace Statecraft\Marking;

use Statecraft\Definition\Definition;
use Statecraft\Exception\InvalidMarking;
use Statecraft\Exception\MarkingStoreError;

/**
 * Port between the machine and wherever the subject keeps its current state.
 */
interface MarkingStore
{
    /**
     * Returns an empty marking when the subject has no state yet; the machine then uses the initial marking.
     *
     * @throws InvalidMarking
     * @throws MarkingStoreError
     */
    public function read(Definition $definition, object $subject): Marking;

    /**
     * @throws MarkingStoreError
     */
    public function write(Definition $definition, object $subject, Marking $marking): void;
}
