<?php

declare(strict_types=1);

namespace Statecraft\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statecraft\Attribute\AttributeLoader;
use Statecraft\Audit\AuditEntry;
use Statecraft\Audit\DefaultSubjectIdResolver;
use Statecraft\Audit\Migrations;
use Statecraft\Audit\PdoAuditTrail;
use Statecraft\Audit\SystemClock;
use Statecraft\Exception\AuditTrailError;
use Statecraft\Machine;
use Statecraft\Marking\PropertyMarkingStore;
use Statecraft\Tests\Fixtures\Order;
use Statecraft\Tests\Fixtures\OrderStatus;
use stdClass;

#[CoversClass(PdoAuditTrail::class)]
#[CoversClass(Migrations::class)]
#[CoversClass(DefaultSubjectIdResolver::class)]
#[CoversClass(SystemClock::class)]
final class PdoAuditTrailTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        Migrations::apply($this->pdo);
    }

    public function testRoundTripsEntries(): void
    {
        $trail = new PdoAuditTrail($this->pdo);
        $at = new DateTimeImmutable('2026-03-01 12:30:45.123456', new DateTimeZone('Europe/Berlin'));

        $trail->record(new AuditEntry('order', '42', 'pay', ['new'], ['paid'], 'alice', $at, ['receipt' => 'Ä-1', 'n' => 3]));
        $trail->record(new AuditEntry('order', '42', 'ship', ['paid'], ['shipped'], null, $at->modify('+1 hour')));
        $trail->record(new AuditEntry('order', '43', 'pay', ['new'], ['paid'], null, $at));

        $history = $trail->history('order', '42');

        self::assertCount(2, $history);
        self::assertSame('pay', $history[0]->transition);
        self::assertSame(['new'], $history[0]->from);
        self::assertSame(['paid'], $history[0]->to);
        self::assertSame('alice', $history[0]->actor);
        self::assertSame(['receipt' => 'Ä-1', 'n' => 3], $history[0]->metadata);
        self::assertSame('2026-03-01T11:30:45.123456+00:00', $history[0]->occurredAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame($at->getTimestamp(), $history[0]->occurredAt->getTimestamp());
        self::assertNull($history[1]->actor);
        self::assertSame([], $history[1]->metadata);
        self::assertSame([], $trail->history('invoice', '42'));
    }

    public function testMachineWritesToPdoTrail(): void
    {
        $trail = new PdoAuditTrail($this->pdo);
        $machine = new Machine((new AttributeLoader())->load(OrderStatus::class), new PropertyMarkingStore('status'), audit: $trail);
        $order = new Order('A-1');

        $machine->apply($order, 'pay', actor: 'bob');
        $machine->apply($order, 'ship');

        $history = $trail->history('order', 'A-1');
        self::assertSame(['pay', 'ship'], array_map(static fn(AuditEntry $e): string => $e->transition, $history));
        self::assertEqualsWithDelta(time(), $history[0]->occurredAt->getTimestamp(), 5);
    }

    public function testRejectsUnsafeTableNames(): void
    {
        new PdoAuditTrail($this->pdo, 'audit.statecraft_audit');

        $this->expectException(AuditTrailError::class);
        $this->expectExceptionMessage('Invalid audit table name "x; DROP TABLE y".');
        new PdoAuditTrail($this->pdo, 'x; DROP TABLE y');
    }

    public function testWrapsDatabaseAndSerialisationErrors(): void
    {
        $trail = new PdoAuditTrail($this->pdo, 'missing_table');
        try {
            $trail->history('order', '1');
            self::fail('Expected exception');
        } catch (AuditTrailError $e) {
            self::assertStringStartsWith('Audit query failed:', $e->getMessage());
        }

        $this->expectException(AuditTrailError::class);
        $this->expectExceptionMessage('Audit metadata of transition "pay" is not JSON serialisable');
        (new PdoAuditTrail($this->pdo))->record(new AuditEntry('order', '1', 'pay', ['new'], ['paid'], null, new DateTimeImmutable(), ['bad' => \NAN]));
    }

    public function testDetectsCorruptedRows(): void
    {
        $this->pdo->exec("INSERT INTO statecraft_audit (machine, subject_id, transition_name, from_places, to_places, actor, metadata, occurred_at) VALUES ('order', '9', 'pay', '{\"a\":1}', '[]', NULL, '{}', '2026-01-01 00:00:00')");

        $this->expectException(AuditTrailError::class);
        $this->expectExceptionMessage('Corrupted audit row: column "from_places" is not a list.');
        (new PdoAuditTrail($this->pdo))->history('order', '9');
    }

    public function testShipsMigrationsForSupportedDrivers(): void
    {
        foreach (['sqlite', 'mysql', 'pgsql'] as $driver) {
            self::assertFileExists(Migrations::path($driver));
            self::assertStringContainsString('CREATE TABLE statecraft_audit', Migrations::sql($driver));
        }

        $this->expectException(AuditTrailError::class);
        $this->expectExceptionMessage('No audit migration for PDO driver "oci"; available: sqlite, mysql, pgsql.');
        Migrations::path('oci');
    }

    public function testDefaultSubjectIdResolver(): void
    {
        $resolver = new DefaultSubjectIdResolver();

        self::assertSame('o-1', $resolver->idOf(new Order('o-1')));
        self::assertSame('7', $resolver->idOf(new class {
            public function getId(): int
            {
                return 7;
            }
        }));

        $this->expectException(AuditTrailError::class);
        $this->expectExceptionMessage('Cannot determine the id of stdClass');
        $resolver->idOf(new stdClass());
    }
}
