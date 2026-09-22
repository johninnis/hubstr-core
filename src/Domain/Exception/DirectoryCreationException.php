<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

final class DirectoryCreationException extends HubstrException
{
    public static function forPath(string $path, string $reason): self
    {
        return new self(sprintf('Failed to create directory %s: %s', $path, $reason));
    }
}
