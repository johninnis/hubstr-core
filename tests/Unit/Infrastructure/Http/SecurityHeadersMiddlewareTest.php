<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Infrastructure\Http\SecurityHeadersMiddleware;
use League\Uri\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    #[DataProvider('baselineHeaders')]
    public function testGivesEveryResponseTheBaselineHeader(string $header, string $value): void
    {
        self::assertSame($value, $this->respondWith(new Response(HttpStatus::OK))->getHeader($header));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function baselineHeaders(): iterable
    {
        yield 'content types are not sniffed' => ['x-content-type-options', 'nosniff'];
        yield 'no referrer is sent onwards' => ['referrer-policy', 'no-referrer'];
    }

    public function testKeepsTheValueAServiceChoseForItself(): void
    {
        $chosen = new Response(HttpStatus::OK, ['referrer-policy' => 'same-origin']);

        self::assertSame('same-origin', $this->respondWith($chosen)->getHeader('referrer-policy'));
    }

    private function respondWith(Response $response): Response
    {
        $next = new ClosureRequestHandler(static fn (Request $request): Response => $response);
        $request = new Request($this->createStub(Client::class), 'GET', Http::new('http://127.0.0.1/'));

        return new SecurityHeadersMiddleware()->handleRequest($request, $next);
    }
}
