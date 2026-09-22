<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Templating;

use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;
use Innis\Hubstr\Core\Infrastructure\Filesystem\Directory;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Override;

final readonly class LatteTemplateRenderer implements TemplateRendererInterface
{
    private function __construct(private Engine $engine)
    {
    }

    public static function create(string $templateDirectory, string $cacheDirectory): self
    {
        Directory::atPath($cacheDirectory)->ensure();

        $engine = new Engine();
        $engine->setTempDirectory($cacheDirectory);
        $engine->setLoader(new FileLoader($templateDirectory));

        return new self($engine);
    }

    #[Override]
    public function render(string $template, array $parameters): string
    {
        return $this->engine->renderToString($template, $parameters);
    }
}
