<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\ValueObject;

use InvalidArgumentException;

final readonly class HttpBinding
{
    private const int IPV4_BITS = 32;
    private const int IPV6_BITS = 128;
    private const string PREFIX_LENGTH = '/^(?:0|[1-9]\d*)$/';

    /**
     * @param non-empty-string       $host
     * @param int<1, 65535>          $port
     * @param list<non-empty-string> $trustedProxies
     */
    private function __construct(
        private string $host,
        private int $port,
        private array $trustedProxies,
    ) {
    }

    /**
     * @param list<non-empty-string> $trustedProxies
     */
    public static function create(string $host, int $port, array $trustedProxies): self
    {
        if ('' === $host || false === filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException(sprintf('host must be an IP address to bind to, got %s', $host));
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('port must be between 1 and 65535, got %d', $port));
        }

        $untrustable = array_find($trustedProxies, static fn (string $proxy): bool => !self::isAddressOrBlock($proxy));

        if (null !== $untrustable) {
            throw new InvalidArgumentException(sprintf('trusted_proxies entries must be an IP address or a CIDR block, got %s', $untrustable));
        }

        return new self($host, $port, $trustedProxies);
    }

    private static function isAddressOrBlock(string $proxy): bool
    {
        [$address, $prefix] = [...explode('/', $proxy, 2), null];

        if (false === filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (null === $prefix) {
            return true;
        }

        $widestPrefix = false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? self::IPV6_BITS : self::IPV4_BITS;

        return 1 === preg_match(self::PREFIX_LENGTH, $prefix) && (int) $prefix <= $widestPrefix;
    }

    /**
     * @return non-empty-string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int<1, 65535>
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getTrustedProxies(): array
    {
        return $this->trustedProxies;
    }
}
