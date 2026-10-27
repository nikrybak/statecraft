<?php

declare(strict_types=1);

namespace Statecraft\Guard;

use Closure;
use Psr\Container\ContainerInterface;
use Statecraft\Exception\GuardResolutionFailed;
use Throwable;

/**
 * Turns the guard reference stored on a transition into a Guard instance.
 *
 * Strings are looked up in the PSR-11 container first (so guards can have dependencies),
 * then instantiated directly when they name a Guard class with a no-argument constructor.
 */
final class GuardResolver
{
    /** @var array<string, Guard> */
    private array $resolved = [];

    public function __construct(private readonly ?ContainerInterface $container = null) {}

    /**
     * @throws GuardResolutionFailed
     */
    public function resolve(Guard|Closure|string $guard): Guard
    {
        if ($guard instanceof Guard) {
            return $guard;
        }
        if ($guard instanceof Closure) {
            return new ClosureGuard($guard);
        }

        return $this->resolved[$guard] ??= $this->resolveId($guard);
    }

    private function resolveId(string $id): Guard
    {
        if ($this->container !== null && $this->container->has($id)) {
            try {
                $service = $this->container->get($id);
            } catch (Throwable $e) {
                throw new GuardResolutionFailed(\sprintf('Container failed to build guard "%s": %s', $id, $e->getMessage()), 0, $e);
            }
            if (!$service instanceof Guard) {
                throw new GuardResolutionFailed(\sprintf('Service "%s" does not implement %s.', $id, Guard::class));
            }

            return $service;
        }

        if (!class_exists($id)) {
            throw new GuardResolutionFailed(\sprintf('Guard "%s" is neither a container service nor an existing class.', $id));
        }
        if (!is_subclass_of($id, Guard::class)) {
            throw new GuardResolutionFailed(\sprintf('Class "%s" does not implement %s.', $id, Guard::class));
        }

        try {
            return new $id();
        } catch (Throwable $e) {
            throw new GuardResolutionFailed(\sprintf('Guard "%s" could not be instantiated without arguments; register it in the container.', $id), 0, $e);
        }
    }
}
