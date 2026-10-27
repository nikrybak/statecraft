<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use RuntimeException;

final class AuditTrailError extends RuntimeException implements StatecraftException {}
