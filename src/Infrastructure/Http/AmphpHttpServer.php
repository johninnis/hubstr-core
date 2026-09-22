<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Closure;
use Innis\Hubstr\Core\Application\Port\ServerInterface;
use Override;

final readonly class AmphpHttpServer implements ServerInterface
{
    public function __construct(
        private SocketHttpServer $server,
        private RequestHandler $handler,
        private ErrorHandler $errorHandler,
    ) {
    }

    #[Override]
    public function start(): void
    {
        $this->server->start($this->handler, $this->errorHandler);
    }

    #[Override]
    public function stop(): void
    {
        $this->server->stop();
    }

    #[Override]
    public function onStop(Closure $callback): void
    {
        $this->server->onStop($callback);
    }
}
