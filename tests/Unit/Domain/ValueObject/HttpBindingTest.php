<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\ValueObject\HttpBinding;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HttpBindingTest extends TestCase
{
    public function testExposesTheAddressItBindsTo(): void
    {
        $binding = HttpBinding::create('0.0.0.0', 8080, ['10.0.0.1']);

        self::assertSame('0.0.0.0', $binding->getHost());
        self::assertSame(8080, $binding->getPort());
        self::assertSame(['10.0.0.1'], $binding->getTrustedProxies());
    }

    /**
     * @param non-empty-string $host
     */
    #[DataProvider('bindableHosts')]
    public function testAcceptsAnIpLiteral(string $host): void
    {
        self::assertSame($host, HttpBinding::create($host, 8080, [])->getHost());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function bindableHosts(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'all IPv4 interfaces' => ['0.0.0.0'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'all IPv6 interfaces' => ['::'];
    }

    public function testRejectsAHostnameBecauseOnlyAnIpCanBeBoundTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host must be an IP address to bind to, got localhost');

        HttpBinding::create('localhost', 8080, []);
    }

    #[DataProvider('unbindablePorts')]
    public function testRejectsAPortOutsideTheRangeASocketCanBindTo(int $port): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('port must be between 1 and 65535, got %d', $port));

        HttpBinding::create('127.0.0.1', $port, []);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unbindablePorts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'one past the highest port' => [65536];
    }

    #[DataProvider('bindablePortBounds')]
    public function testAcceptsThePortsAtEitherEndOfTheRange(int $port): void
    {
        self::assertSame($port, HttpBinding::create('127.0.0.1', $port, [])->getPort());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function bindablePortBounds(): iterable
    {
        yield 'lowest' => [1];
        yield 'highest' => [65535];
    }

    /**
     * @param non-empty-string $proxy
     */
    #[DataProvider('trustableProxies')]
    public function testAcceptsATrustedProxyGivenAsAnAddressOrABlock(string $proxy): void
    {
        self::assertSame([$proxy], HttpBinding::create('127.0.0.1', 8080, [$proxy])->getTrustedProxies());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function trustableProxies(): iterable
    {
        yield 'IPv4 address' => ['10.0.0.1'];
        yield 'IPv6 address' => ['::1'];
        yield 'IPv4 block' => ['10.0.0.0/8'];
        yield 'widest IPv4 block' => ['0.0.0.0/0'];
        yield 'single-host IPv4 block' => ['10.0.0.1/32'];
        yield 'IPv6 block' => ['fd00::/8'];
        yield 'single-host IPv6 block' => ['::1/128'];
    }

    /**
     * @param non-empty-string $proxy
     */
    #[DataProvider('untrustableProxies')]
    public function testRejectsATrustedProxyThatIsNeitherAnAddressNorABlock(string $proxy): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('trusted_proxies entries must be an IP address or a CIDR block, got %s', $proxy));

        HttpBinding::create('127.0.0.1', 8080, [$proxy]);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function untrustableProxies(): iterable
    {
        yield 'hostname' => ['proxy.example.com'];
        yield 'address with a trailing space' => ['127.0.0.1 '];
        yield 'octet out of range' => ['300.1.1.1'];
        yield 'IPv4 prefix too long' => ['10.0.0.0/33'];
        yield 'IPv6 prefix too long' => ['fd00::/129'];
        yield 'prefix that is not a number' => ['10.0.0.0/eight'];
        yield 'empty prefix' => ['10.0.0.0/'];
        yield 'prefix with a leading zero' => ['10.0.0.0/08'];
        yield 'negative prefix' => ['10.0.0.0/-1'];
    }

    public function testCanOnlyBeBuiltThroughTheValidatingConstructor(): void
    {
        self::assertTrue(new ReflectionClass(HttpBinding::class)->getConstructor()?->isPrivate());
    }
}
