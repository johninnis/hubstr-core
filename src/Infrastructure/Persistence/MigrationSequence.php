<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Persistence;

use FilesystemIterator;
use Innis\Hubstr\Core\Domain\Exception\InvalidMigrationSequenceException;
use Innis\Hubstr\Core\Domain\Exception\MigrationsNotReadableException;
use UnexpectedValueException;

final readonly class MigrationSequence
{
    private const string FILE_NAME = '/^(\d{4})-[a-z0-9]+(?:-[a-z0-9]+)*\.sql$/';
    private const string SQL_EXTENSION = 'sql';

    /**
     * @param non-empty-list<Migration> $migrations
     */
    private function __construct(
        private array $migrations,
    ) {
    }

    public static function fromDirectory(string $directory): self
    {
        try {
            $entries = new FilesystemIterator($directory, FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::SKIP_DOTS);
        } catch (UnexpectedValueException $unreadable) {
            throw MigrationsNotReadableException::forPath($directory, $unreadable->getMessage());
        }

        $names = array_values(array_filter(
            array_keys(iterator_to_array($entries)),
            static fn (string $entry): bool => '' !== $entry && self::SQL_EXTENSION === pathinfo($entry, PATHINFO_EXTENSION),
        ));

        if ([] === $names) {
            throw InvalidMigrationSequenceException::noMigrations($directory);
        }

        $migrations = array_map(static fn (string $name): Migration => self::migration($directory, $name), $names);
        usort($migrations, static fn (Migration $a, Migration $b): int => [$a->getVersion(), $a->getName()] <=> [$b->getVersion(), $b->getName()]);

        $misplaced = array_find_key($migrations, static fn (Migration $migration, int $index): bool => $migration->getVersion() !== $index + 1);

        if (null !== $misplaced) {
            throw InvalidMigrationSequenceException::outOfSequence($misplaced + 1, $migrations[$misplaced]->getName());
        }

        return new self($migrations);
    }

    /**
     * @return list<Migration>
     */
    public function pendingAfter(int $version): array
    {
        return array_values(array_filter(
            $this->migrations,
            static fn (Migration $migration): bool => $migration->getVersion() > $version,
        ));
    }

    /**
     * @return positive-int
     */
    public function getLatestVersion(): int
    {
        return count($this->migrations);
    }

    /**
     * @param non-empty-string $name
     */
    private static function migration(string $directory, string $name): Migration
    {
        if (1 !== preg_match(self::FILE_NAME, $name, $matches) || (int) $matches[1] < 1) {
            throw InvalidMigrationSequenceException::malformedName($name);
        }

        return new Migration((int) $matches[1], $name, self::read(rtrim($directory, '/').'/'.$name, $name));
    }

    /**
     * @return non-empty-string
     */
    private static function read(string $path, string $name): string
    {
        error_clear_last();
        $sql = @file_get_contents($path);
        $problem = error_get_last();

        if (false === $sql || null !== $problem) {
            throw MigrationsNotReadableException::forPath($path, $problem['message'] ?? 'no reason reported');
        }

        return '' === trim($sql) ? throw InvalidMigrationSequenceException::emptyMigration($name) : $sql;
    }
}
