<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Guard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Statecraft\Exception\GuardResolutionFailed;
use Statecraft\Guard\ClosureGuard;
use Statecraft\Guard\Guard;
use Statecraft\Guard\GuardResolver;
use Statecraft\Guard\GuardResult;
use Statecraft\Marking\Marking;
use Statecraft\Tests\Fixtures\Machines;
use Statecraft\Tests\Fixtures\Order;
use Statecraft\Tests\Fixtures\PositiveAmount;
use Statecraft\TransitionContext;
use stdClass;

#[CoversClass(GuardResolver::class)]
#[CoversClass(ClosureGuard::class)]
#[CoversClass(GuardResult::class)]
final class GuardResolverTest extends TestCase
{
    public function testResolvesInstancesClosuresAndClassNames(): void
    {
        $resolver = new GuardResolver();
        $instance = new PositiveAmount();

        self::assertSame($instance, $resolver->resolve($instance));
        self::assertInstanceOf(ClosureGuard::class, $resolver->resolve(static fn(): bool => true));

        $byClass = $resolver->resolve(PositiveAmount::class);
        self::assertInstanceOf(PositiveAmount::class, $byClass);
        self::assertSame($byClass, $resolver->resolve(PositiveAmount::class), 'resolved guards are reused');
    }

    public function testPrefersContainerServices(): void
    {
        $service = new PositiveAmount();
        $resolver = new GuardResolver($this->container(['guard.amount' => $service, PositiveAmount::class => $service]));

        self::assertSame($service, $resolver->resolve('guard.amount'));
        self::assertSame($service, $resolver->resolve(PositiveAmount::class));
    }

    public function testReportsUnresolvableGuards(): void
    {
        $failing = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('boom');
            }

            public function has(string $id): bool
            {
                return $id === 'broken';
            }
        };

        $cases = [
            [new GuardResolver($this->container(['not.guard' => new stdClass()])), 'not.guard', 'Service "not.guard" does not implement'],
            [new GuardResolver($failing), 'broken', 'Container failed to build guard "broken": boom'],
            [new GuardResolver(), 'Missing\\Guard', 'Guard "Missing\\Guard" is neither a container service nor an existing class.'],
            [new GuardResolver(), stdClass::class, 'Class "stdClass" does not implement'],
            [new GuardResolver(), NeedsDependency::class, 'could not be instantiated without arguments'],
        ];

        foreach ($cases as [$resolver, $id, $message]) {
            try {
                $resolver->resolve($id);
                self::fail('Expected exception for ' . $id);
            } catch (GuardResolutionFailed $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testClosureGuardNormalisesResults(): void
    {
        $context = $this->context();

        self::assertTrue((new ClosureGuard(static fn(): bool => true))->check($context)->allowed);
        $denied = (new ClosureGuard(static fn(): bool => false))->check($context);
        self::assertFalse($denied->allowed);
        self::assertSame('condition returned false', $denied->reason);
        self::assertSame('nope', (new ClosureGuard(static fn(): GuardResult => GuardResult::deny('nope')))->check($context)->reason);
        self::assertSame('', GuardResult::allow()->reason);

        $this->expectException(GuardResolutionFailed::class);
        $this->expectExceptionMessage('Guard closure of transition "open" must return bool or Statecraft\Guard\GuardResult, got string.');
        (new ClosureGuard(static fn(): string => 'yes'))->check($context);
    }

    private function context(): TransitionContext
    {
        $door = Machines::door();

        return new TransitionContext($door, new Order(), $door->transition('open'), new Marking('closed'));
    }

    /**
     * @param array<string, object> $services
     */
    private function container(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services
             */
            public function __construct(private array $services) {}

            public function get(string $id): object
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}

final class NeedsDependency implements Guard
{
    public function __construct(private readonly string $dependency) {}

    public function check(TransitionContext $context): GuardResult
    {
        return GuardResult::when($this->dependency !== '', 'empty');
    }
}
