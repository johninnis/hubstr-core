<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Tests\Fake\FakeTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class ErrorPageResponderTest extends TestCase
{
    public function testRendersTheErrorTemplateWithTheStatusMessageAndSiteInfo(): void
    {
        $renderer = new FakeTemplateRenderer();

        $this->responder($renderer)->respond(HttpStatus::NOT_FOUND, 'Not Found');

        self::assertSame('error.latte', $renderer->lastTemplate);
        self::assertSame([
            'status' => HttpStatus::NOT_FOUND,
            'message' => 'Not Found',
            'name' => 'Hubstr Service',
            'version' => '1.2.3',
        ], $renderer->lastParameters);
    }

    public function testServesTheRenderedBodyAsHtml(): void
    {
        $response = $this->responder(new FakeTemplateRenderer())->respond(HttpStatus::NOT_FOUND, 'Not Found');

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        self::assertSame('text/html; charset=utf-8', $response->getHeader('content-type'));
    }

    public function testKeepsTheMessageOutOfTheStatusLine(): void
    {
        $response = $this->responder(new FakeTemplateRenderer())->respond(HttpStatus::NOT_FOUND, 'No Such Page');

        self::assertSame('Not Found', $response->getReason());
    }

    public function testAMessageCarryingALineBreakCannotReachTheResponseHead(): void
    {
        $response = $this->responder(new FakeTemplateRenderer())->respond(HttpStatus::NOT_FOUND, "Unknown app: nope\r\nSet-Cookie: session=attacker");

        self::assertSame(['Not Found', false], [$response->getReason(), $response->hasHeader('set-cookie')]);
    }

    private function responder(FakeTemplateRenderer $renderer): ErrorPageResponder
    {
        return new ErrorPageResponder('error.latte', $renderer, new StaticSiteInfoProvider(new SiteInfo('Hubstr Service', '1.2.3')));
    }
}
