<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;

final readonly class LandingPageResponder
{
    public function __construct(
        private string $indexTemplate,
        private TemplateRendererInterface $renderer,
        private SiteInfo $siteInfo,
    ) {
    }

    public function respond(): Response
    {
        $body = $this->renderer->render($this->indexTemplate, [
            'name' => $this->siteInfo->getName(),
            'owner_npub' => $this->siteInfo->getOwnerNpub(),
            'version' => $this->siteInfo->getVersion(),
        ]);

        return new Response(HttpStatus::OK, ['content-type' => 'text/html; charset=utf-8'], $body);
    }
}
