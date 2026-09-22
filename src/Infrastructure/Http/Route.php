<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Closure;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use InvalidArgumentException;

final readonly class Route
{
    /**
     * @param Closure(Request): Response $handler
     */
    public function __construct(
        private HttpMethod $method,
        private string $pattern,
        private Closure $handler,
    ) {
        if (!str_starts_with($pattern, '/')) {
            throw new InvalidArgumentException(sprintf('A route pattern must start with "/", got "%s"', $pattern));
        }
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return Closure(Request): Response
     */
    public function getHandler(): Closure
    {
        return $this->handler;
    }
}
