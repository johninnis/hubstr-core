<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Override;

final readonly class TemplatedErrorHandler implements ErrorHandler
{
    public function __construct(
        private ErrorPageResponder $errorPage,
    ) {
    }

    #[Override]
    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        return $this->errorPage->respond($status, $reason ?? HttpStatus::getReason($status));
    }
}
