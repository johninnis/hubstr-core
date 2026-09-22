<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

use Throwable;

final class MigrationFailedException extends HubstrException
{
    public static function forMigration(string $name, Throwable $failure): self
    {
        return new self(sprintf('Migration %s failed and was rolled back: %s', $name, $failure->getMessage()), 0, $failure);
    }

    public static function couldNotStart(string $name, Throwable $failure): self
    {
        return new self(sprintf('Migration %s could not start: %s', $name, $failure->getMessage()), 0, $failure);
    }

    public static function endedItsTransaction(string $name, ?Throwable $failure = null): self
    {
        return new self(sprintf('Migration %s ended the transaction it ran in, so it was neither atomic nor rolled back: a migration must not contain BEGIN, COMMIT or ROLLBACK', $name), 0, $failure);
    }

    public static function versionUnreadable(): self
    {
        return new self('Could not read the schema version from the database');
    }
}
