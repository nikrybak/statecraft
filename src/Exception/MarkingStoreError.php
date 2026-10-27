<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use RuntimeException;

/**
 * The marking store could not read or write the subject (missing property, wrong method signature).
 */
final class MarkingStoreError extends RuntimeException implements StatecraftException {}
