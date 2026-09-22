<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

final class InvalidMigrationSequenceException extends HubstrException
{
    public static function noMigrations(string $directory): self
    {
        return new self(sprintf('No migrations found in %s', $directory));
    }

    public static function emptyMigration(string $name): self
    {
        return new self(sprintf('Migration %s is empty', $name));
    }

    public static function malformedName(string $name): self
    {
        return new self(sprintf('Migration %s is not named NNNN-description.sql', $name));
    }

    public static function outOfSequence(int $expectedVersion, string $foundName): self
    {
        return new self(sprintf('Migrations must be numbered contiguously from 0001: expected migration %04d but found %s', $expectedVersion, $foundName));
    }
}
