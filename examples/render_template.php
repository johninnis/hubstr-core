<?php

declare(strict_types=1);

use Innis\Hubstr\Core\Infrastructure\Templating\LatteTemplateRenderer;

require __DIR__.'/../vendor/autoload.php';

$renderer = LatteTemplateRenderer::create(__DIR__.'/templates', dirname(__DIR__).'/var/example/latte');

echo $renderer->render('example.latte', [
    'title' => 'Hubstr',
    'body' => '<script>alert("escaped by default")</script>',
    'link' => 'https://example.com/about',
]).PHP_EOL;
