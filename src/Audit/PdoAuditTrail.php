<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use PDO;
use PDOException;
use PDOStatement;
use Statecraft\Exception\AuditTrailError;

/**
 * Stores the audit trail in a SQL table created by one of the scripts in resources/migrations.
 *
 * Timestamps are written in UTC without an offset so the same value fits MySQL DATETIME(6),
 * PostgreSQL TIMESTAMP(6) and SQLite TEXT; they are read back as UTC.
 */
final readonly class PdoAuditTrail implements AuditTrail
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private PDO $pdo,
        private string $table = 'statecraft_audit',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table) !== 1) {
            throw new AuditTrailError(\sprintf('Invalid audit table name "%s".', $table));
        }
    }

    public function record(AuditEntry $entry): void
    {
        $sql = \sprintf(
            'INSERT INTO %s (machine, subject_id, transition_name, from_places, to_places, actor, metadata, occurred_at)'
            . ' VALUES (:machine, :subject_id, :transition_name, :from_places, :to_places, :actor, :metadata, :occurred_at)',
            $this->table,
        );

        try {
            $this->execute($sql, [
                'machine' => $entry->machine,
                'subject_id' => $entry->subjectId,
                'transition_name' => $entry->transition,
                'from_places' => json_encode($entry->from, JSON_THROW_ON_ERROR),
                'to_places' => json_encode($entry->to, JSON_THROW_ON_ERROR),
                'actor' => $entry->actor,
                'metadata' => json_encode((object) $entry->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $entry->occurredAt->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT),
            ]);
        } catch (JsonException $e) {
            throw new AuditTrailError(\sprintf('Audit metadata of transition "%s" is not JSON serialisable: %s', $entry->transition, $e->getMessage()), 0, $e);
        }
    }

    public function history(string $machine, string $subjectId): array
    {
        $sql = \sprintf(
            'SELECT machine, subject_id, transition_name, from_places, to_places, actor, metadata, occurred_at'
            . ' FROM %s WHERE machine = :machine AND subject_id = :subject_id ORDER BY id',
            $this->table,
        );

        $statement = $this->execute($sql, ['machine' => $machine, 'subject_id' => $subjectId]);

        $entries = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @var array<string, mixed> $row */
            $entries[] = $this->hydrate($row);
        }

        return $entries;
    }

    /**
     * @param array<string, scalar|null> $parameters
     */
    private function execute(string $sql, array $parameters): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);
            if ($statement === false || !$statement->execute($parameters)) {
                throw new AuditTrailError(\sprintf('Audit query failed: %s', implode(' ', array_map(strval(...), $this->pdo->errorInfo()))));
            }
        } catch (PDOException $e) {
            throw new AuditTrailError(\sprintf('Audit query failed: %s', $e->getMessage()), 0, $e);
        }

        return $statement;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuditEntry
    {
        try {
            $metadata = json_decode($this->string($row, 'metadata'), true, 512, JSON_THROW_ON_ERROR);
            $occurredAt = new DateTimeImmutable($this->string($row, 'occurred_at'), new DateTimeZone('UTC'));
        } catch (JsonException|Exception $e) {
            throw new AuditTrailError(\sprintf('Corrupted audit row: %s', $e->getMessage()), 0, $e);
        }

        $actor = $row['actor'] ?? null;

        return new AuditEntry(
            machine: $this->string($row, 'machine'),
            subjectId: $this->string($row, 'subject_id'),
            transition: $this->string($row, 'transition_name'),
            from: $this->places($row, 'from_places'),
            to: $this->places($row, 'to_places'),
            actor: \is_scalar($actor) ? (string) $actor : null,
            occurredAt: $occurredAt,
            metadata: \is_array($metadata) ? $this->stringKeys($metadata) : [],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!\is_scalar($value)) {
            throw new AuditTrailError(\sprintf('Corrupted audit row: column "%s" is %s.', $column, get_debug_type($value)));
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function places(array $row, string $column): array
    {
        try {
            $decoded = json_decode($this->string($row, $column), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AuditTrailError(\sprintf('Corrupted audit row: column "%s" is not JSON.', $column), 0, $e);
        }
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            throw new AuditTrailError(\sprintf('Corrupted audit row: column "%s" is not a list.', $column));
        }

        return array_map(static fn(mixed $p): string => \is_scalar($p) ? (string) $p : '', $decoded);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private function stringKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
