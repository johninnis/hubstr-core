<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

interface TemplateRendererInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $template, array $parameters): string;
}
