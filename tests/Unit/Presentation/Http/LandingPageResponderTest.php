<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Core\Application\Port\SiteInfoProviderInterface;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Presentation\Http\LandingPageResponder;
use Innis\Hubstr\Core\Tests\Fake\FakeTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class LandingPageResponderTest extends TestCase
{
    public function testRendersTheIndexTemplateWithTheSiteIdentity(): void
    {
        $renderer = new FakeTemplateRenderer();

        new LandingPageResponder('index.latte', $renderer, new StaticSiteInfoProvider(new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner')))->respond();

        self::assertSame('index.latte', $renderer->lastTemplate);
        self::assertSame([
            'name' => 'Hubstr Service',
            'owner_npub' => 'npub1owner',
            'version' => '1.2.3',
        ], $renderer->lastParameters);
    }

    public function testReadsTheIdentityOnEveryRender(): void
    {
        $renderer = new FakeTemplateRenderer();
        $provider = $this->createStub(SiteInfoProviderInterface::class);
        $provider->method('getSiteInfo')->willReturn(new SiteInfo('Before', '1.2.3'), new SiteInfo('After', '1.2.3'));
        $responder = new LandingPageResponder('index.latte', $renderer, $provider);

        $responder->respond();
        $responder->respond();

        self::assertSame('After', $renderer->lastParameters['name']);
    }

    public function testServesTheRenderedBodyAsHtml(): void
    {
        $response = new LandingPageResponder('index.latte', new FakeTemplateRenderer(), new StaticSiteInfoProvider(new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner')))->respond();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame('text/html; charset=utf-8', $response->getHeader('content-type'));
    }
}
