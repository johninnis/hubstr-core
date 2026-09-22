<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Override;

final readonly class SecurityHeadersMiddleware implements Middleware
{
    /** @var array<non-empty-string, non-empty-string> */
    private const array BASELINE = [
        'x-content-type-options' => 'nosniff',
        'referrer-policy' => 'no-referrer',
    ];

    #[Override]
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $response = $requestHandler->handleRequest($request);

        foreach (self::BASELINE as $header => $value) {
            if (!$response->hasHeader($header)) {
                $response->setHeader($header, $value);
            }
        }

        return $response;
    }
}
