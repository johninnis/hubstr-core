<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Closure;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testExposesWhatItRoutes(): void
    {
        $route = new Route(HttpMethod::Put, '/upload', self::handler());

        self::assertSame([HttpMethod::Put, '/upload'], [$route->getMethod(), $route->getPattern()]);
    }

    #[DataProvider('unmatchablePatterns')]
    public function testRejectsAPatternNoRequestPathCouldMatch(string $pattern): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('A route pattern must start with "/", got "%s"', $pattern));

        new Route(HttpMethod::Get, $pattern, self::handler());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unmatchablePatterns(): iterable
    {
        yield 'empty' => [''];
        yield 'no leading slash' => ['upload'];
    }

    /**
     * @return Closure(Request): Response
     */
    private static function handler(): Closure
    {
        return static fn (Request $request): Response => new Response();
    }
}
