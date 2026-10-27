<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use UnexpectedValueException;

/**
 * The marking store returned or received a value that does not fit the definition.
 */
final class InvalidMarking extends UnexpectedValueException implements StatecraftException {}
