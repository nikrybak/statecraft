<?php

declare(strict_types=1);

namespace Statecraft\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExamplesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function examples(): iterable
    {
        yield 'order lifecycle' => ['order-lifecycle.php', 'Next: deliver, return'];
        yield 'document approval' => ['document-approval.php', 'Rows in audit table: 4'];
    }

    #[DataProvider('examples')]
    public function testExampleRuns(string $script, string $expected): void
    {
        $path = \dirname(__DIR__, 2) . '/examples/' . $script;
        $command = \sprintf('%s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($path));

        exec($command, $output, $code);

        self::assertSame(0, $code, implode("\n", $output));
        self::assertContains($expected, $output);
    }
}
