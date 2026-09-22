<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\DefaultExceptionHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\AllowedMethodsMiddleware;
use Amp\Http\Server\Middleware\CompressionMiddleware;
use Amp\Http\Server\Middleware\ConcurrencyLimitingMiddleware;
use Amp\Http\Server\Middleware\ExceptionHandlerMiddleware;
use Amp\Http\Server\Middleware\ForwardedHeaderType;
use Amp\Http\Server\Middleware\ForwardedMiddleware;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerSocketFactory;
use Innis\Hubstr\Core\Application\Port\ServerInterface;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Domain\ValueObject\HttpBinding;
use Psr\Log\LoggerInterface;

use function Amp\Http\Server\Middleware\stackMiddleware;

final readonly class HttpServerFactory
{
    public const string COMPRESSIBLE_CONTENT_TYPES = '#^(?:text/(?!event-stream)[^/]*+|[^/]*+/xml|[^+]*\+xml|application/(?:json|(?:x-)?javascript))$#i';

    public function __construct(
        private LoggerInterface $logger,
        private ServerSocketFactory $socketFactory,
    ) {
    }

    public function createSocketServer(HttpBinding $binding, HttpServerOptions $options): SocketHttpServer
    {
        $server = new SocketHttpServer(
            $this->logger,
            $this->socketFactory,
            new SocketClientFactory($this->logger),
            $this->middleware($binding, $options),
            // Deliberate: the kit refuses a method inside its own stack, not outside it — see ADR-0020
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(logger: $this->logger, bodySizeLimit: $options->getBodySizeLimit()),
        );

        $server->expose(new InternetAddress($binding->getHost(), $binding->getPort()));

        return $server;
    }

    public function createServer(SocketHttpServer $socketServer, RouterDefinition $definition): ServerInterface
    {
        $errorHandler = $definition->getErrorHandler();

        // Deliberate: every error a handler raises is answered inside the kit's stack, never by the driver outside it — see ADR-0020
        $handler = stackMiddleware(
            $this->router($socketServer, $definition),
            new ExceptionHandlerMiddleware(new DefaultExceptionHandler($errorHandler, $this->logger)),
            // Deliberate: amphp leaves a client error a handler lets escape unanswered — see ADR-0009
            new EscapedErrorMiddleware($errorHandler),
            new AllowedMethodsMiddleware($errorHandler, $this->logger, array_column(HttpMethod::cases(), 'value')),
        );

        return new AmphpHttpServer($socketServer, $handler, $errorHandler);
    }

    private function router(SocketHttpServer $server, RouterDefinition $definition): Router
    {
        $router = new Router($server, $this->logger, $definition->getErrorHandler());

        foreach ($definition->getRoutes() as $route) {
            $router->addRoute($route->getMethod()->value, $route->getPattern(), new ClosureRequestHandler($route->getHandler()));
        }

        $router->setFallback(new DocumentRoot($server, $definition->getErrorHandler(), $definition->getPublicDirectory()));

        return $router;
    }

    /**
     * @return list<Middleware>
     */
    private function middleware(HttpBinding $binding, HttpServerOptions $options): array
    {
        return [
            // Deliberate: outermost, so an error answered further in carries them too — see ADR-0013
            new SecurityHeadersMiddleware(),
            new ConcurrencyLimitingMiddleware($options->getConcurrencyLimit()),
            new ForwardedMiddleware(ForwardedHeaderType::XForwardedFor, $binding->getTrustedProxies()),
            // Deliberate: the content-type pattern is ours, not the middleware's default — see ADR-0006
            new CompressionMiddleware(contentRegex: self::COMPRESSIBLE_CONTENT_TYPES),
            ...$options->getMiddleware(),
        ];
    }
}
