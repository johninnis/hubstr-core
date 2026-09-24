<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Presentation\Http;

use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Application\Port\SiteInfoProviderInterface;
use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;

final readonly class ErrorPageResponder
{
    public function __construct(
        private string $errorTemplate,
        private TemplateRendererInterface $renderer,
        private SiteInfoProviderInterface $siteInfoProvider,
    ) {
    }

    // Deliberate: the identity is read on every render, not captured at wiring — see ADR-0021
    public function respond(int $status, string $message): Response
    {
        $siteInfo = $this->siteInfoProvider->getSiteInfo();

        $body = $this->renderer->render($this->errorTemplate, [
            'status' => $status,
            'message' => $message,
            'name' => $siteInfo->getName(),
            'version' => $siteInfo->getVersion(),
        ]);

        // Deliberate: the message is rendered, never sent as the reason phrase — see ADR-0014
        return new Response($status, ['content-type' => 'text/html; charset=utf-8'], $body);
    }
}
