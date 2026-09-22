<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Documentation;

use PHPUnit\Framework\TestCase;

final class QuickStartTest extends TestCase
{
    private const string AUTOLOAD = "require __DIR__.'/../vendor/autoload.php';\n";
    private const string QUICK_START = '/^## Quick Start\n.*?^```php\n(.*?)^```$/ms';

    public function testTheReadmesQuickStartIsTheBodyOfTheExampleItSaysItIs(): void
    {
        $root = dirname(__DIR__, 3);
        $example = (string) file_get_contents($root.'/examples/serve_site.php');
        $readme = (string) file_get_contents($root.'/README.md');

        self::assertSame(1, preg_match(self::QUICK_START, $readme, $quickStart));
        self::assertSame(trim(substr($example, (int) strpos($example, self::AUTOLOAD) + strlen(self::AUTOLOAD))), trim($quickStart[1]));
    }
}
