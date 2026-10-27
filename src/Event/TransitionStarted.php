<?php

declare(strict_types=1);

namespace Statecraft\Event;

/**
 * Guards passed; the marking is about to change. Throwing from a listener aborts the transition.
 */
final readonly class TransitionStarted extends TransitionEvent {}
