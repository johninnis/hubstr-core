<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\Enum;

use Amp\Http\Server\Middleware\AllowedMethodsMiddleware;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
    public function testNamesExactlyTheMethodsTheServerLetsThrough(): void
    {
        self::assertEqualsCanonicalizing(AllowedMethodsMiddleware::DEFAULT_ALLOWED_METHODS, array_column(HttpMethod::cases(), 'value'));
    }
}
