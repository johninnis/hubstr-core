<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Filesystem;

use Innis\Hubstr\Core\Domain\Exception\DirectoryCreationException;
use InvalidArgumentException;

final readonly class Directory
{
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
            throw new InvalidArgumentException('Directory path must not be blank');
        }

        return new self($path);
    }

    public function ensure(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        error_clear_last();

        if (!@mkdir($this->path, 0o755, true) && !is_dir($this->path)) {
            throw DirectoryCreationException::forPath($this->path, error_get_last()['message'] ?? 'no reason reported');
        }
    }
}
