<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\Enum\LogLevel;
use Innis\Hubstr\Core\Domain\ValueObject\ConfigValues;
use Innis\Hubstr\Core\Domain\ValueObject\ServiceRuntimeConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceRuntimeConfigTest extends TestCase
{
    public function testCreatesFromValidConfig(): void
    {
        $config = ServiceRuntimeConfig::fromValues(ConfigValues::fromArray($this->base()));

        self::assertSame('0.0.0.0', $config->getBinding()->getHost());
        self::assertSame(9000, $config->getBinding()->getPort());
        self::assertSame(['10.0.0.1'], $config->getBinding()->getTrustedProxies());
        self::assertSame('/tmp/service.sqlite', $config->getDatabasePath());
        self::assertSame(LogLevel::Debug, $config->getLogLevel());
    }

    public function testItsKeyListNamesEveryKeyItReads(): void
    {
        ConfigValues::fromArray($this->base())->rejectUnknownKeys(...ServiceRuntimeConfig::KEYS);

        self::assertEqualsCanonicalizing(array_keys($this->base()), ServiceRuntimeConfig::KEYS);
    }

    public function testAppliesDefaults(): void
    {
        $config = ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([
            'database_path' => '/tmp/service.sqlite',
            'port' => 8080,
        ]));

        self::assertSame('127.0.0.1', $config->getBinding()->getHost());
        self::assertSame(LogLevel::Info, $config->getLogLevel());
        self::assertSame(['127.0.0.1'], $config->getBinding()->getTrustedProxies());
    }

    public function testFallsBackToDefaultPort(): void
    {
        $config = ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([
            'database_path' => '/tmp/service.sqlite',
        ]), 8080);

        self::assertSame(8080, $config->getBinding()->getPort());
    }

    public function testAConfiguredPortWinsOverTheDefault(): void
    {
        $config = ServiceRuntimeConfig::fromValues(ConfigValues::fromArray($this->base()), 8080);

        self::assertSame(9000, $config->getBinding()->getPort());
    }

    public function testReadsALogLevelRegardlessOfCase(): void
    {
        $config = ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'log_level' => 'WARNING']));

        self::assertSame(LogLevel::Warning, $config->getLogLevel());
    }

    public function testRejectsMissingPortWithoutDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port is required');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([
            'database_path' => '/tmp/service.sqlite',
        ]));
    }

    public function testRejectsOutOfRangePort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port must be between 1 and 65535, got 70000');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'port' => 70000]));
    }

    public function testRejectsNonIntegerPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port must be an integer');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'port' => '8080']));
    }

    public function testRejectsNonStringHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host must be a non-empty string');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'host' => ['0.0.0.0']]));
    }

    public function testRejectsAHostThatIsNotAnIpAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host must be an IP address to bind to');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'host' => 'example.com']));
    }

    public function testRejectsNonStringLogLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('log_level must be a non-empty string');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'log_level' => ['debug']]));
    }

    public function testRejectsAnUnknownLogLevelAndNamesTheOnesItAccepts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('log_level must be one of debug, info, notice, warning, error, critical, alert, emergency, got verbose');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'log_level' => 'verbose']));
    }

    public function testRejectsNonListTrustedProxies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trusted_proxies must be a list of non-empty strings');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'trusted_proxies' => '10.0.0.1']));
    }

    public function testRejectsNonStringTrustedProxyEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trusted_proxies must be a list of non-empty strings');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'trusted_proxies' => ['10.0.0.1', 22]]));
    }

    public function testRejectsEmptyDatabasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('database_path');

        ServiceRuntimeConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'database_path' => '']));
    }

    public function testRejectsALogPathBecauseTheLogGoesToStandardOutputOnly(): void
    {
        $values = ConfigValues::fromArray([...$this->base(), 'log_path' => '/var/log/service.log']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config key: log_path');

        $values->rejectUnknownKeys(...ServiceRuntimeConfig::KEYS);
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return [
            'host' => '0.0.0.0',
            'port' => 9000,
            'database_path' => '/tmp/service.sqlite',
            'log_level' => 'debug',
            'trusted_proxies' => ['10.0.0.1'],
        ];
    }
}
