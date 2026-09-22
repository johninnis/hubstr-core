<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\Enum\LogLevel;
use InvalidArgumentException;

final readonly class ServiceRuntimeConfig
{
    /** @var list<string> */
    public const array KEYS = ['host', 'port', 'trusted_proxies', 'database_path', 'log_level'];
    private const string LOOPBACK = '127.0.0.1';

    /**
     * @param non-empty-string $databasePath
     */
    private function __construct(
        private HttpBinding $binding,
        private string $databasePath,
        private LogLevel $logLevel,
    ) {
    }

    public function getBinding(): HttpBinding
    {
        return $this->binding;
    }

    /**
     * @return non-empty-string
     */
    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }

    public function getLogLevel(): LogLevel
    {
        return $this->logLevel;
    }

    public static function fromValues(ConfigValues $values, ?int $defaultPort = null): self
    {
        return new self(
            binding: HttpBinding::create(
                host: $values->optionalString('host') ?? self::LOOPBACK,
                port: self::port($values, $defaultPort),
                trustedProxies: $values->optionalStringList('trusted_proxies') ?? [self::LOOPBACK],
            ),
            databasePath: $values->string('database_path'),
            logLevel: self::logLevel($values->optionalString('log_level')),
        );
    }

    private static function port(ConfigValues $values, ?int $default): int
    {
        if (null === $default) {
            return $values->int('port');
        }

        return $values->optionalInt('port') ?? $default;
    }

    private static function logLevel(?string $name): LogLevel
    {
        if (null === $name) {
            return LogLevel::Info;
        }

        return LogLevel::tryFrom(strtolower($name)) ?? throw new InvalidArgumentException(sprintf('log_level must be one of %s, got %s', implode(', ', array_column(LogLevel::cases(), 'value')), $name));
    }
}
