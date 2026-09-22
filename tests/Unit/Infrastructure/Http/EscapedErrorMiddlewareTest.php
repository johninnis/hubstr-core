<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ClientException;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Infrastructure\Http\EscapedErrorMiddleware;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use Throwable;

final class EscapedErrorMiddlewareTest extends TestCase
{
    public function testPassesAHandledResponseThroughUntouched(): void
    {
        $handled = new Response(HttpStatus::OK, [], 'handled');
        $next = new ClosureRequestHandler(static fn (Request $request): Response => $handled);

        $response = new EscapedErrorMiddleware($this->errorHandler())->handleRequest($this->request(), $next);

        self::assertSame($handled, $response);
    }

    public function testAnswersAClientErrorTheHandlerLetEscapeWithItsStatusAndReason(): void
    {
        $response = $this->answerTo(new ClientException($this->client(), 'Payload too large', HttpStatus::PAYLOAD_TOO_LARGE));

        self::assertSame([HttpStatus::PAYLOAD_TOO_LARGE, 'Payload too large'], [$response->getStatus(), $response->getReason()]);
    }

    public function testClosesTheConnectionAfterAClientError(): void
    {
        $response = $this->answerTo(new ClientException($this->client(), 'Payload too large', HttpStatus::PAYLOAD_TOO_LARGE));

        self::assertSame('close', $response->getHeader('connection'));
    }

    public function testAnswersBadRequestWhenTheClientErrorCarriesNoHttpErrorStatus(): void
    {
        $response = $this->answerTo(new ClientException($this->client(), 'Malformed request'));

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testAnswersAnHttpErrorTheHandlerThrewWithItsStatusAndReason(): void
    {
        $response = $this->answerTo(new HttpErrorException(HttpStatus::GONE, 'Gone for good'));

        self::assertSame([HttpStatus::GONE, 'Gone for good'], [$response->getStatus(), $response->getReason()]);
    }

    public function testKeepsTheConnectionOpenAfterAnHttpErrorTheHandlerThrew(): void
    {
        $response = $this->answerTo(new HttpErrorException(HttpStatus::GONE));

        self::assertFalse($response->hasHeader('connection'));
    }

    private function answerTo(Throwable $thrown): Response
    {
        $next = new ClosureRequestHandler(static fn (Request $request): Response => throw $thrown);

        return new EscapedErrorMiddleware($this->errorHandler())->handleRequest($this->request(), $next);
    }

    private function errorHandler(): ErrorHandler
    {
        $errorHandler = $this->createStub(ErrorHandler::class);
        $errorHandler->method('handleError')->willReturnCallback(
            static function (int $status, ?string $reason): Response {
                $response = new Response();
                $response->setStatus($status, $reason);

                return $response;
            },
        );

        return $errorHandler;
    }

    private function request(): Request
    {
        return new Request($this->client(), 'POST', Http::new('http://127.0.0.1/echo'));
    }

    private function client(): Client
    {
        return $this->createStub(Client::class);
    }
}
