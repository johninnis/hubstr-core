<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\Forwarded;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\TimeoutCancellation;
use Innis\Hubstr\Core\Application\Port\ServerInterface;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Domain\ValueObject\HttpBinding;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerFactory;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerOptions;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use Innis\Hubstr\Core\Infrastructure\Http\RouterDefinition;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Presentation\Http\TemplatedErrorHandler;
use Innis\Hubstr\Core\Tests\Fake\FakeTemplateRenderer;
use Innis\Hubstr\Core\Tests\Fake\RecordingLogger;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;
use function Amp\Socket\connect;

final class HttpServerFactoryTest extends TestCase
{
    private const float RESPONSE_TIMEOUT_SECONDS = 5.0;
    private const string SECRET = 'the database password is hunter2';

    private TemporaryDirectory $publicDirectory;
    private RecordingLogger $logger;
    private ?ServerInterface $server = null;
    private int $port = 0;
    private int $requestsInFlight = 0;
    private int $mostRequestsInFlight = 0;

    protected function setUp(): void
    {
        $this->publicDirectory = TemporaryDirectory::create();
        file_put_contents($this->publicDirectory->path('styles.css'), 'body { color: teal; }');
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->publicDirectory->remove();
    }

    public function testDispatchesARouteToItsHandler(): void
    {
        $this->startServer();

        self::assertSame([HttpStatus::OK, 'hello'], $this->fetch('/hello'));
    }

    public function testServesAStaticFileThroughTheFallback(): void
    {
        $this->startServer();

        self::assertSame([HttpStatus::OK, 'body { color: teal; }'], $this->fetch('/styles.css'));
    }

    public function testRendersAnUnknownPathThroughTheErrorHandler(): void
    {
        $this->startServer();

        [$status, $body] = $this->fetch('/missing');

        self::assertSame(HttpStatus::NOT_FOUND, $status);
        self::assertStringContainsString('404', $body);
    }

    public function testAnswersAnUnhandledExceptionWithTheErrorPage(): void
    {
        $this->startServer();

        [$status, $body] = $this->fetch('/boom');

        self::assertSame([HttpStatus::INTERNAL_SERVER_ERROR, true], [$status, str_contains($body, '500')]);
    }

    public function testKeepsAnUnhandledExceptionsMessageOffTheClientAndInTheLog(): void
    {
        $this->startServer();

        [, $body] = $this->fetch('/boom');
        $logged = implode("\n", array_column($this->logger->records, 'message'));

        self::assertSame([false, true], [str_contains($body, self::SECRET), str_contains($logged, self::SECRET)]);
    }

    public function testAnswersAnHttpErrorAHandlerThrowsWithItsStatusAndItsReasonOnThePage(): void
    {
        $this->startServer();

        [$status, $body] = $this->fetch('/gone');

        self::assertSame([HttpStatus::GONE, true], [$status, str_contains($body, 'Gone for good')]);
    }

    public function testRefusesAMethodTheKitDoesNotAcceptBeforeRouting(): void
    {
        $this->startServer();

        [$status] = $this->send(self::request('TRACE', '/hello'));

        self::assertSame(HttpStatus::METHOD_NOT_ALLOWED, $status);
    }

    public function testRunsAStopCallbackBeforeStopReturns(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16), ['127.0.0.1']);
        $drained = false;
        $this->server->onStop(static function () use (&$drained): void {
            $drained = true;
        });
        $this->server->start();

        $this->server->stop();

        self::assertTrue($drained);
    }

    public function testAnswersABodyOverTheConfiguredLimitThroughTheErrorHandler(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16, bodySizeLimit: 8), ['127.0.0.1']);
        $this->server->start();

        [$status, $body] = $this->send(self::post('/echo', str_repeat('x', 64)));

        self::assertSame(HttpStatus::PAYLOAD_TOO_LARGE, $status);
        self::assertStringContainsString('413', $body);
    }

    public function testAcceptsARequestBodyWithinTheConfiguredLimit(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16, bodySizeLimit: 8), ['127.0.0.1']);
        $this->server->start();

        self::assertSame([HttpStatus::OK, 'small'], $this->send(self::post('/echo', 'small')));
    }

    public function testBelievesAForwardedAddressFromATrustedProxy(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16), ['127.0.0.1']);
        $this->server->start();

        self::assertSame([HttpStatus::OK, '203.0.113.9'], $this->send(self::request('GET', '/forwarded', "X-Forwarded-For: 203.0.113.9\r\n")));
    }

    public function testIgnoresAForwardedAddressFromAPeerItDoesNotTrust(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16), ['10.255.255.1']);
        $this->server->start();

        self::assertSame([HttpStatus::OK, '127.0.0.1'], $this->send(self::request('GET', '/forwarded', "X-Forwarded-For: 203.0.113.9\r\n")));
    }

    public function testRunsTheCallersMiddlewareInsideItsOwnSoItSeesTheForwardedAddress(): void
    {
        $tagging = new class implements Middleware {
            #[Override]
            public function handleRequest(Request $request, RequestHandler $requestHandler): Response
            {
                $sawOnTheWayIn = $request->hasAttribute(Forwarded::class) ? 'the forwarded address' : 'nothing';
                $response = $requestHandler->handleRequest($request);
                $response->setHeader('x-caller-saw', $sawOnTheWayIn);

                return $response;
            }
        };
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16, middleware: [$tagging]), ['127.0.0.1']);
        $this->server->start();

        $response = $this->exchange(self::request('GET', '/hello', "X-Forwarded-For: 203.0.113.9\r\n"));

        self::assertStringContainsString("x-caller-saw: the forwarded address\r\n", $response);
    }

    #[DataProvider('everyKindOfResponse')]
    public function testSendsTheBaselineSecurityHeadersOnEveryKindOfResponse(string $method, string $target): void
    {
        $this->startServer();

        $response = $this->exchange(self::request($method, $target));

        self::assertStringContainsString("x-content-type-options: nosniff\r\n", $response);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function everyKindOfResponse(): iterable
    {
        yield 'a routed page' => ['GET', '/hello'];
        yield 'a static file' => ['GET', '/styles.css'];
        yield 'an error page' => ['GET', '/missing'];
        yield 'the error page for an unhandled exception' => ['GET', '/boom'];
        yield 'the error page for an error a handler threw' => ['GET', '/gone'];
        yield 'the refusal of a method the kit does not accept' => ['TRACE', '/hello'];
    }

    public function testAResponseTheCallersMiddlewareAnswersWithStillCarriesTheBaselineHeaders(): void
    {
        $answering = new class implements Middleware {
            #[Override]
            public function handleRequest(Request $request, RequestHandler $requestHandler): Response
            {
                return new Response(HttpStatus::NO_CONTENT);
            }
        };
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16, middleware: [$answering]), ['127.0.0.1']);
        $this->server->start();

        self::assertStringContainsString("x-content-type-options: nosniff\r\n", $this->exchange(self::request('GET', '/hello')));
    }

    public function testCompressesAPage(): void
    {
        $this->startServer();

        self::assertStringContainsString("content-encoding: gzip\r\n", $this->exchange(self::request('GET', '/page', "Accept-Encoding: gzip\r\n")));
    }

    public function testNeverCompressesAnEventStream(): void
    {
        $this->startServer();

        self::assertStringNotContainsString('content-encoding', $this->exchange(self::request('GET', '/stream', "Accept-Encoding: gzip\r\n")));
    }

    public function testHoldsRequestsBeyondTheConcurrencyLimitUntilOneFinishes(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 1), ['127.0.0.1']);
        $this->server->start();

        await([async(fn (): array => $this->fetch('/busy')), async(fn (): array => $this->fetch('/busy'))]);

        self::assertSame(1, $this->mostRequestsInFlight);
    }

    private function startServer(): void
    {
        $this->server = $this->buildServer(HttpServerOptions::create(concurrencyLimit: 16), ['127.0.0.1']);
        $this->server->start();
    }

    /**
     * @param list<non-empty-string> $trustedProxies
     */
    private function buildServer(HttpServerOptions $options, array $trustedProxies): ServerInterface
    {
        $this->port = self::freePort();

        $errorHandler = new TemplatedErrorHandler(
            new ErrorPageResponder('error.latte', new FakeTemplateRenderer(), new StaticSiteInfoProvider(new SiteInfo('Hubstr Service', '1.2.3'))),
        );

        $definition = new RouterDefinition(
            [
                new Route(HttpMethod::Get, '/hello', static fn (Request $request): Response => new Response(HttpStatus::OK, [], 'hello')),
                new Route(HttpMethod::Post, '/echo', static fn (Request $request): Response => new Response(HttpStatus::OK, [], $request->getBody()->buffer())),
                new Route(HttpMethod::Get, '/forwarded', static fn (Request $request): Response => new Response(HttpStatus::OK, [], self::clientAddress($request))),
                new Route(HttpMethod::Get, '/page', static fn (Request $request): Response => new Response(HttpStatus::OK, ['content-type' => 'text/html'], str_repeat('page ', 400))),
                new Route(HttpMethod::Get, '/stream', static fn (Request $request): Response => new Response(HttpStatus::OK, ['content-type' => 'text/event-stream'], str_repeat("data: x\n\n", 300))),
                new Route(HttpMethod::Get, '/boom', static fn (Request $request): Response => throw new RuntimeException(self::SECRET)),
                new Route(HttpMethod::Get, '/gone', static fn (Request $request): Response => throw new HttpErrorException(HttpStatus::GONE, 'Gone for good')),
                new Route(HttpMethod::Get, '/busy', $this->busy(...)),
            ],
            $errorHandler,
            $this->publicDirectory->getPath(),
        );

        $binding = HttpBinding::create('127.0.0.1', $this->port, $trustedProxies);

        $factory = new HttpServerFactory($this->logger, new ResourceServerSocketFactory());

        return $factory->createServer($factory->createSocketServer($binding, $options), $definition);
    }

    /**
     * @return array{int, string}
     */
    private function fetch(string $target): array
    {
        return $this->send(self::request('GET', $target));
    }

    private static function clientAddress(Request $request): string
    {
        $forwarded = $request->hasAttribute(Forwarded::class) ? $request->getAttribute(Forwarded::class) : null;
        $address = $forwarded instanceof Forwarded ? $forwarded->getFor() : $request->getClient()->getRemoteAddress();

        return $address instanceof InternetAddress ? $address->getAddress() : $address->toString();
    }

    private function busy(Request $request): Response
    {
        $this->mostRequestsInFlight = max($this->mostRequestsInFlight, ++$this->requestsInFlight);
        delay(0.2);
        --$this->requestsInFlight;

        return new Response(HttpStatus::OK, [], 'done');
    }

    private static function request(string $method, string $target, string $extraHeaders = ''): string
    {
        return sprintf("%s %s HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n%s\r\n", $method, $target, $extraHeaders);
    }

    private static function post(string $target, string $body): string
    {
        return sprintf("POST %s HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\nContent-Length: %d\r\n\r\n%s", $target, strlen($body), $body);
    }

    /**
     * @return array{int, string}
     */
    private function send(string $rawRequest): array
    {
        $response = $this->exchange($rawRequest);
        $headerEnd = strpos($response, "\r\n\r\n");

        if (false === $headerEnd || 1 !== preg_match('#^HTTP/1\.\d (\d{3})#', $response, $matches)) {
            throw new RuntimeException('malformed HTTP response: '.$response);
        }

        return [(int) $matches[1], substr($response, $headerEnd + 4)];
    }

    private function exchange(string $rawRequest): string
    {
        $socket = connect('127.0.0.1:'.$this->port);
        $socket->write($rawRequest);

        $response = '';
        $deadline = new TimeoutCancellation(self::RESPONSE_TIMEOUT_SECONDS);
        while (null !== ($chunk = $socket->read($deadline))) {
            $response .= $chunk;
        }

        return $response;
    }

    /**
     * @return int<1, 65535>
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if (false === $socket) {
            throw new RuntimeException('could not reserve a port: '.$errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        $colon = false === $name ? false : strrpos($name, ':');

        if (false === $name || false === $colon) {
            throw new RuntimeException('could not read the reserved port');
        }

        $port = (int) substr($name, $colon + 1);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('the reserved port is out of range: '.$port);
        }

        return $port;
    }
}
