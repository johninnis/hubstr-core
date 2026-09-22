<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Persistence;

use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqliteDatabaseTest extends TestCase
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

    public function testInMemoryConnectionIsUsable(): void
    {
        $pdo = SqliteDatabase::inMemory()->connect();
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO t (id) VALUES (1)');

        self::assertSame(1, (int) $this->queryColumn($pdo, 'SELECT COUNT(*) FROM t'));
    }

    public function testAnInMemoryDatabaseWritesNothingToDisk(): void
    {
        SqliteDatabase::inMemory()->connect();

        self::assertFileDoesNotExist(':memory:');
    }

    public function testCreatesTheDatabaseFileAndItsParentDirectory(): void
    {
        $path = $this->directory->path('nested/app.sqlite');

        SqliteDatabase::atPath($path)->connect();

        self::assertFileExists($path);
    }

    public function testCreatesADatabaseOnlyItsOwnerCanRead(): void
    {
        $path = $this->directory->path('private.sqlite');
        $held = SqliteDatabase::atPath($path)->connect();

        $held->exec('CREATE TABLE secrets (value TEXT)');

        self::assertSame(['0600', '0600', '0600'], array_map(self::mode(...), [$path, $path.'-wal', $path.'-shm']));
    }

    public function testClosesAnExistingDatabaseToOtherUsersWhenItConnects(): void
    {
        $path = $this->directory->path('adopted.sqlite');
        $held = SqliteDatabase::atPath($path)->connect();
        $held->exec('CREATE TABLE secrets (value TEXT)');
        array_map(static fn (string $file): bool => chmod($file, 0o644), [$path, $path.'-wal', $path.'-shm']);

        SqliteDatabase::atPath($path)->connect();

        self::assertSame(['0640', '0640', '0640'], array_map(self::mode(...), [$path, $path.'-wal', $path.'-shm']));
    }

    public function testKeepsTheGroupAccessAnOperatorGranted(): void
    {
        $path = $this->directory->path('shared.sqlite');
        SqliteDatabase::atPath($path)->connect()->exec('CREATE TABLE secrets (value TEXT)');
        chmod($path, 0o660);

        SqliteDatabase::atPath($path)->connect();

        self::assertSame('0660', self::mode($path));
    }

    public function testLeavesTheProcessUmaskAsItFoundIt(): void
    {
        $inherited = umask(0o022);

        try {
            SqliteDatabase::atPath($this->directory->path('umask.sqlite'))->connect();

            self::assertSame(0o022, umask());
        } finally {
            umask($inherited);
        }
    }

    #[DataProvider('connectionSettings')]
    public function testConfiguresEveryConnectionItOpens(string $pragma, string $expected): void
    {
        $pdo = SqliteDatabase::atPath($this->directory->path('app.sqlite'))->connect();

        self::assertSame($expected, strtolower($this->queryColumn($pdo, 'PRAGMA '.$pragma)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function connectionSettings(): iterable
    {
        yield 'write-ahead log' => ['journal_mode', 'wal'];
        yield 'normal synchronous mode' => ['synchronous', '1'];
        yield 'foreign keys enforced' => ['foreign_keys', '1'];
        yield 'five second busy timeout' => ['busy_timeout', '5000'];
        yield 'eight megabyte page cache' => ['cache_size', '-8000'];
        yield 'memory-mapped reads' => ['mmap_size', '268435456'];
        yield 'temporary tables in memory' => ['temp_store', '2'];
        yield 'capped write-ahead log' => ['journal_size_limit', '67108864'];
    }

    public function testRaisesExceptionsOnError(): void
    {
        $pdo = SqliteDatabase::inMemory()->connect();

        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testTheSameDatabaseValueOpensIndependentConnections(): void
    {
        $database = SqliteDatabase::atPath($this->directory->path('app.sqlite'));

        $writer = $database->connect();
        $writer->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $writer->exec('INSERT INTO t (id) VALUES (1)');

        self::assertSame(1, (int) $this->queryColumn($database->connect(), 'SELECT COUNT(*) FROM t'));
    }

    public function testABlankPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqliteDatabase::atPath('   ');
    }

    public function testADatabaseValueSurvivesSerialisationSoItCanCrossAProcessBoundary(): void
    {
        $database = SqliteDatabase::atPath($this->directory->path('app.sqlite'));
        $database->connect()->exec("CREATE TABLE notes (body TEXT); INSERT INTO notes VALUES ('written before the boundary')");

        $restored = unserialize(serialize($database));

        self::assertInstanceOf(SqliteDatabase::class, $restored);
        self::assertSame('written before the boundary', $this->queryColumn($restored->connect(), 'SELECT body FROM notes'));
    }

    private function queryColumn(PDO $pdo, string $sql): string
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        $value = $statement->fetchColumn();

        return is_scalar($value) ? (string) $value : '';
    }

    private static function mode(string $file): string
    {
        clearstatcache(true, $file);

        return substr(sprintf('%o', fileperms($file)), -4);
    }
}
