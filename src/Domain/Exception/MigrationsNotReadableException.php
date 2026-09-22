<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

final class MigrationsNotReadableException extends HubstrException
{
    public static function forPath(string $path, string $reason): self
    {
        return new self(sprintf('Could not read migrations at %s: %s', $path, $reason));
    }
}
