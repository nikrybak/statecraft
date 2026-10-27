<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use Throwable;

/**
 * Marker for every exception thrown by the library, so callers can catch them in one place.
 */
interface StatecraftException extends Throwable {}
