<?php

declare(strict_types=1);

namespace Statecraft\Exception;

use RuntimeException;

final class GuardResolutionFailed extends RuntimeException implements StatecraftException {}
