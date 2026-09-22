<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Persistence;

use Innis\Hubstr\Core\Infrastructure\Filesystem\Directory;
use InvalidArgumentException;
use PDO;

final readonly class SqliteDatabase
{
    private const string IN_MEMORY_PATH = ':memory:';
    private const int OWNER_ONLY_UMASK = 0o077;
    private const int PERMISSION_BITS = 0o7777;
    private const int OTHER_USERS = 0o007;

    /** @var list<string> */
    private const array FILE_SUFFIXES = ['', '-wal', '-shm'];

    /** @var array<string, int|string> */
    private const array PRAGMAS = [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'foreign_keys' => 'ON',
        'busy_timeout' => 5000,
        'cache_size' => -8000,
        'mmap_size' => 268435456,
        'temp_store' => 'MEMORY',
        'journal_size_limit' => 67108864,
    ];

    /**
     * @param non-empty-string $path
     */
    private function __construct(
        private string $path,
    ) {
    }

    public static function atPath(string $path): self
    {
        if ('' === $path || '' === trim($path)) {
            throw new InvalidArgumentException('SQLite database path must not be blank');
        }

        return new self($path);
    }

    public static function inMemory(): self
    {
        return new self(self::IN_MEMORY_PATH);
    }

    public function connect(): PDO
    {
        if ($this->isInMemory()) {
            return $this->open();
        }

        Directory::atPath(dirname($this->path))->ensure();

        // Deliberate: the file is created owner-only and closed to other users — see ADR-0012
        $inherited = umask(self::OWNER_ONLY_UMASK);

        try {
            $pdo = $this->open();
        } finally {
            umask($inherited);
        }

        $this->closeToOtherUsers();

        return $pdo;
    }

    private function open(): PDO
    {
        $pdo = new PDO('sqlite:'.$this->path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (self::PRAGMAS as $pragma => $value) {
            $pdo->exec("PRAGMA {$pragma}={$value}");
        }

        return $pdo;
    }

    private function closeToOtherUsers(): void
    {
        foreach (array_filter(array_map(fn (string $suffix): string => $this->path.$suffix, self::FILE_SUFFIXES), is_file(...)) as $file) {
            clearstatcache(true, $file);
            $mode = fileperms($file) & self::PERMISSION_BITS;

            if (0 !== ($mode & self::OTHER_USERS)) {
                @chmod($file, $mode & ~self::OTHER_USERS);
            }
        }
    }

    private function isInMemory(): bool
    {
        return self::IN_MEMORY_PATH === $this->path;
    }
}
