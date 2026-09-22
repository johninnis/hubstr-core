<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Presentation\Http\LandingPageResponder;
use Innis\Hubstr\Core\Tests\Fake\FakeTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class LandingPageResponderTest extends TestCase
{
    public function testRendersTheIndexTemplateWithTheSiteIdentity(): void
    {
        $renderer = new FakeTemplateRenderer();

        new LandingPageResponder('index.latte', $renderer, new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner'))->respond();

        self::assertSame('index.latte', $renderer->lastTemplate);
        self::assertSame([
            'name' => 'Hubstr Service',
            'owner_npub' => 'npub1owner',
            'version' => '1.2.3',
        ], $renderer->lastParameters);
    }

    public function testServesTheRenderedBodyAsHtml(): void
    {
        $response = new LandingPageResponder('index.latte', new FakeTemplateRenderer(), new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner'))->respond();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame('text/html; charset=utf-8', $response->getHeader('content-type'));
    }
}
