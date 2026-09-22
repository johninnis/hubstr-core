<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Fake;

use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;
use Override;

final class FakeTemplateRenderer implements TemplateRendererInterface
{
    public string $lastTemplate = '';

    /** @var array<string, mixed> */
    public array $lastParameters = [];

    /**
     * @param array<string, mixed> $parameters
     */
    #[Override]
    public function render(string $template, array $parameters): string
    {
        $this->lastTemplate = $template;
        $this->lastParameters = $parameters;

        return implode('', array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $parameters,
        ));
    }
}
