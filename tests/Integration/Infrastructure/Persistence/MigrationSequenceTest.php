<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Persistence;

use Innis\Hubstr\Core\Domain\Exception\InvalidMigrationSequenceException;
use Innis\Hubstr\Core\Domain\Exception\MigrationsNotReadableException;
use Innis\Hubstr\Core\Infrastructure\Persistence\Migration;
use Innis\Hubstr\Core\Infrastructure\Persistence\MigrationSequence;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class MigrationSequenceTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testOrdersMigrationsByVersionNotByDirectoryOrder(): void
    {
        $this->touchAll('0010-tenth.sql', '0002-second.sql', '0001-first.sql', '0003-third.sql', '0004-d.sql', '0005-e.sql', '0006-f.sql', '0007-g.sql', '0008-h.sql', '0009-i.sql');

        $versions = array_map(
            static fn (Migration $migration): int => $migration->getVersion(),
            MigrationSequence::fromDirectory($this->directory->getPath())->pendingAfter(0),
        );

        self::assertSame(range(1, 10), $versions);
    }

    public function testYieldsOnlyTheMigrationsAfterAVersion(): void
    {
        $this->touchAll('0001-first.sql', '0002-second.sql', '0003-third.sql');

        $names = array_map(
            static fn (Migration $migration): string => $migration->getName(),
            MigrationSequence::fromDirectory($this->directory->getPath())->pendingAfter(1),
        );

        self::assertSame(['0002-second.sql', '0003-third.sql'], $names);
    }

    public function testReportsTheNewestVersion(): void
    {
        $this->touchAll('0001-first.sql', '0002-second.sql');

        self::assertSame(2, MigrationSequence::fromDirectory($this->directory->getPath())->getLatestVersion());
    }

    public function testIgnoresFilesThatAreNotSql(): void
    {
        $this->touchAll('0001-first.sql', 'README.md');

        self::assertSame(1, MigrationSequence::fromDirectory($this->directory->getPath())->getLatestVersion());
    }

    public function testRejectsAGapInTheSequence(): void
    {
        $this->touchAll('0001-first.sql', '0003-third.sql');

        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('expected migration 0002 but found 0003-third.sql');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testRejectsTwoMigrationsClaimingOneVersion(): void
    {
        $this->touchAll('0001-first.sql', '0001-other.sql');

        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('expected migration 0002 but found 0001-other.sql');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testRejectsASequenceThatDoesNotStartAtOne(): void
    {
        $this->touchAll('0002-second.sql');

        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('expected migration 0001 but found 0002-second.sql');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testRejectsASqlFileWhoseNameCarriesNoVersion(): void
    {
        $this->touchAll('0001-first.sql', 'add-colour.sql');

        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('add-colour.sql is not named NNNN-description.sql');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testRejectsADirectoryHoldingNoMigrations(): void
    {
        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('No migrations found in '.$this->directory->getPath());

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testFailsWhenTheDirectoryDoesNotExist(): void
    {
        $this->expectException(MigrationsNotReadableException::class);
        $this->expectExceptionMessage('No such file or directory');

        MigrationSequence::fromDirectory($this->directory->path('absent'));
    }

    public function testCarriesEachMigrationsSql(): void
    {
        file_put_contents($this->directory->path('0001-first.sql'), 'CREATE TABLE widgets (id INTEGER);');

        self::assertSame('CREATE TABLE widgets (id INTEGER);', MigrationSequence::fromDirectory($this->directory->getPath())->pendingAfter(0)[0]->getSql());
    }

    public function testRefusesAnEmptyMigrationBecauseItWouldRecordAVersionForNothing(): void
    {
        $this->touchAll('0001-first.sql');
        file_put_contents($this->directory->path('0002-forgotten.sql'), "  \n");

        $this->expectException(InvalidMigrationSequenceException::class);
        $this->expectExceptionMessage('Migration 0002-forgotten.sql is empty');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    public function testFailsWithTheReasonWhenADirectoryStandsWhereAMigrationShould(): void
    {
        mkdir($this->directory->path('0001-first.sql'));

        try {
            $this->expectException(MigrationsNotReadableException::class);
            $this->expectExceptionMessage('Is a directory');

            MigrationSequence::fromDirectory($this->directory->getPath());
        } finally {
            rmdir($this->directory->path('0001-first.sql'));
        }
    }

    public function testFailsWithTheReasonWhenAMigrationCannotBeRead(): void
    {
        symlink($this->directory->path('nowhere'), $this->directory->path('0001-first.sql'));

        $this->expectException(MigrationsNotReadableException::class);
        $this->expectExceptionMessage('No such file or directory');

        MigrationSequence::fromDirectory($this->directory->getPath());
    }

    private function touchAll(string ...$names): void
    {
        foreach ($names as $name) {
            file_put_contents($this->directory->path($name), 'SELECT 1;');
        }
    }
}
