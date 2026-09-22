<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\ErrorHandler;

final readonly class RouterDefinition
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        private array $routes,
        private ErrorHandler $errorHandler,
        private string $publicDirectory,
    ) {
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getErrorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }

    public function getPublicDirectory(): string
    {
        return $this->publicDirectory;
    }
}
