<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ClientException;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Override;

final readonly class EscapedErrorMiddleware implements Middleware
{
    public function __construct(
        private ErrorHandler $errorHandler,
    ) {
    }

    #[Override]
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            return $requestHandler->handleRequest($request);
        } catch (HttpErrorException $error) {
            return $this->errorHandler->handleError($error->getStatus(), $error->getReason(), $request);
        } catch (ClientException $exception) {
            $response = $this->errorHandler->handleError(self::status($exception), $exception->getMessage(), $request);
            $response->setHeader('connection', 'close');

            return $response;
        }
    }

    private static function status(ClientException $exception): int
    {
        $status = $exception->getCode();

        return HttpStatus::isClientError($status) || HttpStatus::isServerError($status) ? $status : HttpStatus::BAD_REQUEST;
    }
}
