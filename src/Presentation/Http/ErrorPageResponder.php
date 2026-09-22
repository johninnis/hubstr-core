<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Presentation\Http;

use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;

final readonly class ErrorPageResponder
{
    public function __construct(
        private string $errorTemplate,
        private TemplateRendererInterface $renderer,
        private SiteInfo $siteInfo,
    ) {
    }

    public function respond(int $status, string $message): Response
    {
        $body = $this->renderer->render($this->errorTemplate, [
            'status' => $status,
            'message' => $message,
            'name' => $this->siteInfo->getName(),
            'version' => $this->siteInfo->getVersion(),
        ]);

        // Deliberate: the message is rendered, never sent as the reason phrase — see ADR-0014
        return new Response($status, ['content-type' => 'text/html; charset=utf-8'], $body);
    }
}
