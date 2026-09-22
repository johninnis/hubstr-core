<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Persistence;

use Innis\Hubstr\Core\Domain\Exception\InvalidMigrationSequenceException;
use Innis\Hubstr\Core\Domain\Exception\MigrationFailedException;
use Innis\Hubstr\Core\Domain\Exception\SchemaAheadOfCodeException;
use Innis\Hubstr\Core\Infrastructure\Persistence\SchemaMigrator;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SchemaMigratorTest extends TestCase
{
    private const string INITIAL = 'CREATE TABLE IF NOT EXISTS widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL);';
    private const string ADD_COLOUR = 'ALTER TABLE widgets ADD COLUMN colour TEXT;';

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testAppliesEveryMigrationToAFreshDatabaseInOrder(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $this->write('0002-add-colour.sql', self::ADD_COLOUR);
        $pdo = SqliteDatabase::inMemory()->connect();

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame(['id', 'name', 'colour'], self::columns($pdo));
    }

    public function testRecordsTheVersionItReached(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $this->write('0002-add-colour.sql', self::ADD_COLOUR);
        $pdo = SqliteDatabase::inMemory()->connect();

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame(2, self::version($pdo));
    }

    public function testAdoptsAnUnversionedDatabaseThatAlreadyHoldsTheInitialSchemaAndKeepsItsData(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = SqliteDatabase::inMemory()->connect();
        $pdo->exec(self::INITIAL);
        $pdo->exec("INSERT INTO widgets (name) VALUES ('kept')");

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame([1, 'kept'], [self::version($pdo), self::scalar($pdo, 'SELECT name FROM widgets')]);
    }

    public function testAppliesOnlyWhatIsPending(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = SqliteDatabase::inMemory()->connect();
        new SchemaMigrator($pdo)->migrate($this->directory->getPath());
        $pdo->exec("INSERT INTO widgets (name) VALUES ('kept')");
        $this->write('0002-add-colour.sql', self::ADD_COLOUR);

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame([2, 'kept'], [self::version($pdo), self::scalar($pdo, 'SELECT name FROM widgets')]);
    }

    public function testRunningAgainWithNothingPendingChangesNothing(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $this->write('0002-add-colour.sql', self::ADD_COLOUR);
        $pdo = SqliteDatabase::inMemory()->connect();
        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame(2, self::version($pdo));
    }

    public function testRollsBackAFailingMigrationWhollyAndKeepsTheVersionBeforeIt(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $this->write('0002-broken.sql', 'ALTER TABLE widgets ADD COLUMN colour TEXT; ALTER TABLE missing ADD COLUMN nope TEXT;');
        $pdo = SqliteDatabase::inMemory()->connect();

        try {
            new SchemaMigrator($pdo)->migrate($this->directory->getPath());
            self::fail('a failing migration should stop the migrator');
        } catch (MigrationFailedException) {
            self::assertSame([1, ['id', 'name']], [self::version($pdo), self::columns($pdo)]);
        }
    }

    public function testNamesTheMigrationThatFailed(): void
    {
        $this->write('0001-broken.sql', 'ALTER TABLE missing ADD COLUMN nope TEXT;');

        $this->expectException(MigrationFailedException::class);
        $this->expectExceptionMessage('Migration 0001-broken.sql failed');

        new SchemaMigrator(SqliteDatabase::inMemory()->connect())->migrate($this->directory->getPath());
    }

    public function testRefusesADatabaseThatIsAheadByASingleVersion(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = SqliteDatabase::inMemory()->connect();
        $pdo->exec('PRAGMA user_version = 2');

        $this->expectException(SchemaAheadOfCodeException::class);

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());
    }

    public function testRefusesADatabaseThatIsAheadOfTheMigrationsItWasGiven(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = SqliteDatabase::inMemory()->connect();
        $pdo->exec('PRAGMA user_version = 3');

        $this->expectException(SchemaAheadOfCodeException::class);
        $this->expectExceptionMessage('Database schema is at version 3 but the newest migration is 1');

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());
    }

    public function testSkipsAMigrationAnotherProcessAppliedWhileItWaitedForTheWriteLock(): void
    {
        $this->write('0001-initial-schema.sql', 'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL);');
        $pdo = new class('sqlite::memory:') extends PDO {
            private bool $raced = false;

            #[Override]
            public function exec(string $statement): int|false
            {
                if ('BEGIN IMMEDIATE' === $statement && !$this->raced) {
                    $this->raced = true;
                    parent::exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL); PRAGMA user_version = 1');
                }

                return parent::exec($statement);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame(1, self::version($pdo));
    }

    public function testRefusesAMigrationThatEndsTheTransactionItRunsIn(): void
    {
        $this->write('0001-commits.sql', 'CREATE TABLE widgets (id INTEGER PRIMARY KEY); COMMIT; CREATE TABLE gadgets (id INTEGER PRIMARY KEY);');
        $pdo = SqliteDatabase::inMemory()->connect();

        try {
            new SchemaMigrator($pdo)->migrate($this->directory->getPath());
            self::fail('a migration that commits for itself should be refused');
        } catch (MigrationFailedException $failure) {
            self::assertSame(
                ['Migration 0001-commits.sql ended the transaction it ran in, so it was neither atomic nor rolled back: a migration must not contain BEGIN, COMMIT or ROLLBACK', 0],
                [$failure->getMessage(), self::version($pdo)],
            );
        }
    }

    public function testReportsTheStatementThatFailedAfterAMigrationEndedItsTransaction(): void
    {
        $this->write('0001-commits.sql', 'CREATE TABLE widgets (id INTEGER PRIMARY KEY); COMMIT; ALTER TABLE missing ADD COLUMN nope TEXT;');

        try {
            new SchemaMigrator(SqliteDatabase::inMemory()->connect())->migrate($this->directory->getPath());
            self::fail('a migration that commits for itself should be refused');
        } catch (MigrationFailedException $failure) {
            self::assertStringContainsString('no such table: missing', (string) $failure->getPrevious()?->getMessage());
        }
    }

    public function testAllowsATriggerBodyWhichIsNotTransactionControl(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL.' CREATE TABLE audit (widget INTEGER); CREATE TRIGGER widgets_audit AFTER INSERT ON widgets BEGIN INSERT INTO audit VALUES (new.id); END;');
        $pdo = SqliteDatabase::inMemory()->connect();

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());

        self::assertSame(1, self::version($pdo));
    }

    public function testNamesTheMigrationItCouldNotStartBecauseTheDatabaseIsLocked(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $database = SqliteDatabase::atPath($this->directory->path('locked.sqlite'));
        $holder = $database->connect();
        $holder->exec('BEGIN IMMEDIATE');
        $waiter = $database->connect();
        $waiter->exec('PRAGMA busy_timeout = 50');

        try {
            new SchemaMigrator($waiter)->migrate($this->directory->getPath());
            self::fail('a locked database should stop the migrator');
        } catch (MigrationFailedException $failure) {
            self::assertSame('Migration 0001-initial-schema.sql could not start: SQLSTATE[HY000]: General error: 5 database is locked', $failure->getMessage());
        } finally {
            $holder->exec('ROLLBACK');
        }
    }

    public function testRefusesAConnectionThatWouldHideAFailingStatement(): void
    {
        $pdo = SqliteDatabase::inMemory()->connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SchemaMigrator needs a connection that throws on error');

        new SchemaMigrator($pdo);
    }

    public function testAppliesNothingWhenALaterMigrationIsEmpty(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $this->write('0002-forgotten.sql', "  \n");
        $pdo = SqliteDatabase::inMemory()->connect();

        try {
            new SchemaMigrator($pdo)->migrate($this->directory->getPath());
            self::fail('an empty migration should be refused');
        } catch (InvalidMigrationSequenceException $failure) {
            self::assertSame(['Migration 0002-forgotten.sql is empty', 0], [$failure->getMessage(), self::version($pdo)]);
        }
    }

    public function testReleasesTheWriteLockWhenAMigrationFailsWithSomethingOtherThanADatabaseError(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = new class('sqlite::memory:') extends PDO {
            #[Override]
            public function exec(string $statement): int|false
            {
                return str_starts_with($statement, 'CREATE TABLE') ? throw new RuntimeException('not a database error') : parent::exec($statement);
            }
        };

        try {
            new SchemaMigrator($pdo)->migrate($this->directory->getPath());
            self::fail('the failure should stop the migrator');
        } catch (MigrationFailedException $failure) {
            self::assertSame([false, 'not a database error'], [$pdo->inTransaction(), $failure->getPrevious()?->getMessage()]);
        }
    }

    public function testFailsWhenTheDatabaseWillNotReportItsSchemaVersion(): void
    {
        $this->write('0001-initial-schema.sql', self::INITIAL);
        $pdo = new class('sqlite::memory:') extends PDO {
            #[Override]
            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                return false;
            }
        };

        $this->expectException(MigrationFailedException::class);
        $this->expectExceptionMessage('Could not read the schema version from the database');

        new SchemaMigrator($pdo)->migrate($this->directory->getPath());
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->directory->path($name), $sql);
    }

    private static function version(PDO $pdo): mixed
    {
        return self::scalar($pdo, 'PRAGMA user_version');
    }

    /**
     * @return list<mixed>
     */
    private static function columns(PDO $pdo): array
    {
        $statement = $pdo->query("SELECT name FROM pragma_table_info('widgets') ORDER BY cid");
        self::assertNotFalse($statement);

        return array_values($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function scalar(PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
