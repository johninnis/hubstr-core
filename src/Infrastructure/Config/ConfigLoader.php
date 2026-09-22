<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Config;

use Innis\Hubstr\Core\Domain\Exception\ConfigFileNotFoundException;
use Innis\Hubstr\Core\Domain\ValueObject\ConfigValues;
use InvalidArgumentException;

final readonly class ConfigLoader
{
    public function __construct(
        private string $environmentVariable,
    ) {
    }

    public function load(string $defaultPath): ConfigValues
    {
        $override = getenv($this->environmentVariable);
        $path = is_string($override) && '' !== $override ? $override : $defaultPath;

        if (!is_file($path)) {
            throw ConfigFileNotFoundException::forPath($path);
        }

        // Deliberate: the file runs in a scope of its own, not this method's — see ADR-0019
        $config = (static fn (string $configFile): mixed => require $configFile)($path);

        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('Config file must return an array: %s', $path));
        }

        return ConfigValues::fromArray($config);
    }
}
