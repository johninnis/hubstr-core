<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Persistence;

use Innis\Hubstr\Core\Domain\Exception\MigrationFailedException;
use Innis\Hubstr\Core\Domain\Exception\SchemaAheadOfCodeException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final readonly class SchemaMigrator
{
    public function __construct(
        private PDO $pdo,
    ) {
        if (PDO::ERRMODE_EXCEPTION !== $pdo->getAttribute(PDO::ATTR_ERRMODE)) {
            throw new InvalidArgumentException('SchemaMigrator needs a connection that throws on error, or a failing migration would pass unnoticed');
        }
    }

    public function migrate(string $migrationsDirectory): void
    {
        $sequence = MigrationSequence::fromDirectory($migrationsDirectory);
        $current = $this->currentVersion();

        if ($current > $sequence->getLatestVersion()) {
            throw SchemaAheadOfCodeException::forVersions($current, $sequence->getLatestVersion());
        }

        foreach ($sequence->pendingAfter($current) as $migration) {
            $this->apply($migration);
        }
    }

    private function apply(Migration $migration): void
    {
        $this->begin($migration);

        try {
            if ($this->currentVersion() < $migration->getVersion()) {
                $this->pdo->exec($migration->getSql());

                if (!$this->pdo->inTransaction()) {
                    throw MigrationFailedException::endedItsTransaction($migration->getName());
                }

                $this->pdo->exec(sprintf('PRAGMA user_version = %d', $migration->getVersion()));
            }

            $this->pdo->exec('COMMIT');
        } catch (Throwable $failure) {
            throw $this->abandon($migration, $failure);
        }
    }

    private function begin(Migration $migration): void
    {
        try {
            // Deliberate: IMMEDIATE takes the write lock before the version is re-read — see ADR-0010
            $this->pdo->exec('BEGIN IMMEDIATE');
        } catch (PDOException $failure) {
            throw MigrationFailedException::couldNotStart($migration->getName(), $failure);
        }
    }

    private function abandon(Migration $migration, Throwable $failure): MigrationFailedException
    {
        $rolledBack = $this->pdo->inTransaction();

        if ($rolledBack) {
            $this->pdo->exec('ROLLBACK');
        }

        return match (true) {
            $failure instanceof MigrationFailedException => $failure,
            $rolledBack => MigrationFailedException::forMigration($migration->getName(), $failure),
            default => MigrationFailedException::endedItsTransaction($migration->getName(), $failure),
        };
    }

    private function currentVersion(): int
    {
        $statement = $this->pdo->query('PRAGMA user_version');
        $version = false === $statement ? null : $statement->fetchColumn();

        return is_int($version) ? $version : throw MigrationFailedException::versionUnreadable();
    }
}
