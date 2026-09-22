<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

final class SchemaAheadOfCodeException extends HubstrException
{
    public static function forVersions(int $databaseVersion, int $latestMigration): self
    {
        return new self(sprintf('Database schema is at version %d but the newest migration is %d: this code is older than the database', $databaseVersion, $latestMigration));
    }
}
