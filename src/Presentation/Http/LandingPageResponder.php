<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Application\Port\SiteInfoProviderInterface;
use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;

final readonly class LandingPageResponder
{
    public function __construct(
        private string $indexTemplate,
        private TemplateRendererInterface $renderer,
        private SiteInfoProviderInterface $siteInfoProvider,
    ) {
    }

    // Deliberate: the identity is read on every render, not captured at wiring — see ADR-0021
    public function respond(): Response
    {
        $siteInfo = $this->siteInfoProvider->getSiteInfo();

        $body = $this->renderer->render($this->indexTemplate, [
            'name' => $siteInfo->getName(),
            'owner_npub' => $siteInfo->getOwnerNpub(),
            'version' => $siteInfo->getVersion(),
        ]);

        return new Response(HttpStatus::OK, ['content-type' => 'text/html; charset=utf-8'], $body);
    }
}
