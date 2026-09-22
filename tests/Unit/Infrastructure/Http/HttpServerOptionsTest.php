<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpServerOptionsTest extends TestCase
{
    public function testExposesWhatItWasGiven(): void
    {
        $middleware = $this->createStub(Middleware::class);

        $options = HttpServerOptions::create(64, 1024, [$middleware]);

        self::assertSame([64, 1024, [$middleware]], [$options->getConcurrencyLimit(), $options->getBodySizeLimit(), $options->getMiddleware()]);
    }

    public function testDefaultsToTheServersOwnBodySizeLimitAndNoExtraMiddleware(): void
    {
        $options = HttpServerOptions::create(64);

        self::assertSame([HttpDriver::DEFAULT_BODY_SIZE_LIMIT, []], [$options->getBodySizeLimit(), $options->getMiddleware()]);
    }

    #[DataProvider('concurrencyLimitsThatAdmitNoRequest')]
    public function testRejectsAConcurrencyLimitThatWouldAdmitNoRequest(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('concurrency limit must be a positive integer, got %d', $limit));

        HttpServerOptions::create($limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function concurrencyLimitsThatAdmitNoRequest(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    public function testRejectsANegativeBodySizeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('body size limit must not be negative, got -1');

        HttpServerOptions::create(64, -1);
    }

    public function testAcceptsABodySizeLimitOfZeroForAServiceThatTakesNoRequestBodies(): void
    {
        self::assertSame(0, HttpServerOptions::create(64, 0)->getBodySizeLimit());
    }
}
