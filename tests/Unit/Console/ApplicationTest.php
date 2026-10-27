<?php

declare(strict_types=1);

namespace Statecraft\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statecraft\Console\Application;
use Statecraft\Console\ClassDiscovery;
use Statecraft\Tests\Fixtures\ArticleStates;
use Statecraft\Tests\Fixtures\Broken\BrokenMachine;
use Statecraft\Tests\Fixtures\OrderStatus;

#[CoversClass(Application::class)]
#[CoversClass(ClassDiscovery::class)]
final class ApplicationTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Fixtures';

    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = \dirname(__DIR__, 3) . '/build/test-output/' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
    }

    public function testDiscoversMachinesByNamespace(): void
    {
        $discovery = new ClassDiscovery();

        self::assertSame(
            [ArticleStates::class, BrokenMachine::class, OrderStatus::class],
            $discovery->machines(self::FIXTURES),
        );
        self::assertSame([BrokenMachine::class], $discovery->machines(self::FIXTURES, 'Statecraft\\Tests\\Fixtures\\Broken'));
        self::assertSame([OrderStatus::class], $discovery->machines(self::FIXTURES . '/OrderStatus.php'));
    }

    public function testValidateReportsOkAndFailures(): void
    {
        [$code, $out] = $this->runCli(['validate', self::FIXTURES]);

        self::assertSame(Application::FAILURE, $code);
        self::assertStringContainsString('OK    order (' . OrderStatus::class . '): 5 states, 4 transitions, state machine', $out);
        self::assertStringContainsString('OK    article_states (' . ArticleStates::class . '): 6 states, 4 transitions, workflow', $out);
        self::assertStringContainsString('FAIL  ' . BrokenMachine::class, $out);
        self::assertStringContainsString('State "orphan" cannot be reached from the initial state.', $out);
        self::assertStringContainsString('State "stuck" has no outgoing transitions and is not marked final (dead end).', $out);
        self::assertStringContainsString('3 machine(s) checked, 1 failed.', $out);
    }

    public function testValidatePassesForValidNamespace(): void
    {
        [$code, $out] = $this->runCli(['validate', self::FIXTURES, '--namespace=Statecraft\\Tests\\Fixtures\\OrderStatus']);

        self::assertSame(Application::SUCCESS, $code);
        self::assertStringContainsString('1 machine(s) checked, 0 failed.', $out);
    }

    public function testRenderPrintsMermaidAndSkipsBrokenMachines(): void
    {
        [$code, $out, $err] = $this->runCli(['render', self::FIXTURES]);

        self::assertSame(Application::FAILURE, $code);
        self::assertStringContainsString("title: order\n", $out);
        self::assertStringContainsString("title: article_states\n", $out);
        self::assertStringContainsString('Skipping ' . BrokenMachine::class, $err);
    }

    public function testRenderWritesDotFilesForOneMachine(): void
    {
        [$code, $out] = $this->runCli(['render', self::FIXTURES, '--namespace=Statecraft\\Tests\\Fixtures\\OrderStatus', '--format=dot', '--machine=order', '--output=' . $this->outputDir]);

        self::assertSame(Application::SUCCESS, $code);
        self::assertStringContainsString('Wrote ', $out);
        self::assertFileExists($this->outputDir . '/order.dot');
        self::assertStringStartsWith('digraph "order"', (string) file_get_contents($this->outputDir . '/order.dot'));
    }

    public function testRenderReportsUnknownMachineAndFormat(): void
    {
        [$code, , $err] = $this->runCli(['render', self::FIXTURES . '/OrderStatus.php', '--machine=nope']);
        self::assertSame(Application::FAILURE, $code);
        self::assertStringContainsString('No machine named "nope".', $err);

        [$code, , $err] = $this->runCli(['render', self::FIXTURES . '/OrderStatus.php', '--format=png']);
        self::assertSame(Application::USAGE, $code);
        self::assertStringContainsString('Unknown format "png"', $err);
    }

    public function testUsageErrors(): void
    {
        [$code, $out] = $this->runCli([]);
        self::assertSame(Application::SUCCESS, $code);
        self::assertStringContainsString('Usage:', $out);

        self::assertSame(Application::SUCCESS, $this->runCli(['validate', '--help'])[0]);

        foreach ([
            [['explode'], 'Unknown command "explode".'],
            [['validate'], 'The validate command needs a <path>.'],
            [['validate', self::FIXTURES . '/missing'], 'is neither a file nor a directory'],
            [['validate', self::FIXTURES, '--bootstrap=missing.php'], 'Bootstrap file "missing.php" does not exist.'],
        ] as [$argv, $message]) {
            [$code, , $err] = $this->runCli($argv);
            self::assertSame(Application::USAGE, $code);
            self::assertStringContainsString($message, $err);
        }
    }

    public function testNoMachinesFound(): void
    {
        [$code, , $err] = $this->runCli(['validate', self::FIXTURES . '/Order.php', '--bootstrap=' . \dirname(__DIR__, 3) . '/vendor/autoload.php']);

        self::assertSame(Application::FAILURE, $code);
        self::assertStringContainsString('No #[StateMachine] classes found', $err);
    }

    /**
     * @param list<string> $argv
     *
     * @return array{int, string, string}
     */
    private function runCli(array $argv): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        $code = (new Application($out, $err))->run($argv);
        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }
}
