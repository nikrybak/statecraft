<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use LogicException;

/**
 * Attributes on a class cannot be turned into a definition (wrong target, missing states, bad values).
 */
final class AttributeMappingError extends LogicException implements StatecraftException {}
