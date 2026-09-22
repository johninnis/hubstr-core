<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware;
use InvalidArgumentException;

final readonly class HttpServerOptions
{
    /**
     * @param positive-int     $concurrencyLimit
     * @param int<0, max>      $bodySizeLimit
     * @param list<Middleware> $middleware
     */
    private function __construct(
        private int $concurrencyLimit,
        private int $bodySizeLimit,
        private array $middleware,
    ) {
    }

    /**
     * @param list<Middleware> $middleware
     */
    public static function create(int $concurrencyLimit, int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT, array $middleware = []): self
    {
        if ($concurrencyLimit < 1) {
            throw new InvalidArgumentException(sprintf('concurrency limit must be a positive integer, got %d', $concurrencyLimit));
        }

        if ($bodySizeLimit < 0) {
            throw new InvalidArgumentException(sprintf('body size limit must not be negative, got %d', $bodySizeLimit));
        }

        return new self($concurrencyLimit, $bodySizeLimit, $middleware);
    }

    /**
     * @return positive-int
     */
    public function getConcurrencyLimit(): int
    {
        return $this->concurrencyLimit;
    }

    /**
     * @return int<0, max>
     */
    public function getBodySizeLimit(): int
    {
        return $this->bodySizeLimit;
    }

    /**
     * @return list<Middleware>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
