<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Templating;

use Innis\Hubstr\Core\Infrastructure\Templating\LatteTemplateRenderer;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use Latte\RuntimeException;
use PHPUnit\Framework\TestCase;

final class LatteTemplateRendererTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
        mkdir($this->directory->path('templates'));
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testRendersATemplateWithParameters(): void
    {
        $this->writeTemplate('greeting.latte', '<h1>Hello {$name}</h1>');

        $renderer = LatteTemplateRenderer::create($this->directory->path('templates'), $this->directory->path('cache'));

        self::assertSame('<h1>Hello World</h1>', $renderer->render('greeting.latte', ['name' => 'World']));
    }

    public function testEscapesParametersByDefault(): void
    {
        $this->writeTemplate('escape.latte', '<p>{$body}</p>');

        $renderer = LatteTemplateRenderer::create($this->directory->path('templates'), $this->directory->path('cache'));

        self::assertSame(
            '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>',
            $renderer->render('escape.latte', ['body' => '<script>alert(1)</script>']),
        );
    }

    public function testNeutralisesAJavascriptUrlInAnAttributeContext(): void
    {
        $this->writeTemplate('attr.latte', '<a href="{$url}">link</a>');

        $renderer = LatteTemplateRenderer::create($this->directory->path('templates'), $this->directory->path('cache'));

        self::assertStringNotContainsString('href="javascript:', $renderer->render('attr.latte', ['url' => 'javascript:alert(1)']));
    }

    public function testRefusesATemplateNameThatLeavesTheTemplateDirectory(): void
    {
        file_put_contents($this->directory->path('outside.latte'), 'leaked');
        $renderer = LatteTemplateRenderer::create($this->directory->path('templates'), $this->directory->path('cache'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not within the allowed path');

        $renderer->render('../outside.latte', []);
    }

    public function testCreatesTheCacheDirectory(): void
    {
        LatteTemplateRenderer::create($this->directory->path('templates'), $this->directory->path('cache'));

        self::assertDirectoryExists($this->directory->path('cache'));
    }

    private function writeTemplate(string $name, string $contents): void
    {
        file_put_contents($this->directory->path('templates/'.$name), $contents);
    }
}
