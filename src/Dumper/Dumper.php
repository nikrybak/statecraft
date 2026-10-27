<?php

declare(strict_types=1);

namespace Statecraft\Dumper;

use Statecraft\Definition\Definition;
use Statecraft\Marking\Marking;

interface Dumper
{
    /**
     * @param Marking|null $highlight places to emphasise, typically the current marking of a subject
     */
    public function dump(Definition $definition, ?Marking $highlight = null): string;

    /**
     * Conventional file extension of the output, without the dot.
     */
    public function extension(): string;
}
