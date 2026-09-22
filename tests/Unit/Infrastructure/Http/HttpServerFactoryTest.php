<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Innis\Hubstr\Core\Infrastructure\Http\HttpServerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpServerFactoryTest extends TestCase
{
    #[DataProvider('compressibleContentTypes')]
    public function testACompressibleContentTypeIsCompressed(string $contentType): void
    {
        self::assertSame(1, preg_match(HttpServerFactory::COMPRESSIBLE_CONTENT_TYPES, $contentType));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function compressibleContentTypes(): iterable
    {
        yield 'html' => ['text/html'];
        yield 'css' => ['text/css'];
        yield 'plain text' => ['text/plain'];
        yield 'json' => ['application/json'];
        yield 'xml' => ['application/xml'];
    }

    public function testAnEventStreamIsNeverCompressed(): void
    {
        self::assertSame(0, preg_match(HttpServerFactory::COMPRESSIBLE_CONTENT_TYPES, 'text/event-stream'));
    }

    public function testAnAlreadyCompressedFormatIsLeftAlone(): void
    {
        self::assertSame(0, preg_match(HttpServerFactory::COMPRESSIBLE_CONTENT_TYPES, 'image/png'));
    }
}
