<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Exception;

final class ConfigFileNotFoundException extends HubstrException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Config file not found: %s', $path));
    }
}
