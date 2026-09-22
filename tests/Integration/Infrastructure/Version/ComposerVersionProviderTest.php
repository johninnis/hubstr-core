<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Version;

use Innis\Hubstr\Core\Infrastructure\Version\ComposerVersionProvider;
use PHPUnit\Framework\TestCase;

final class ComposerVersionProviderTest extends TestCase
{
    public function testReportsRootPackageVersion(): void
    {
        $version = new ComposerVersionProvider()->getVersion();

        self::assertNotSame('', $version);
    }

    public function testADevVersionCarriesAShortCommitReferenceAndAReleaseVersionStandsAlone(): void
    {
        $version = new ComposerVersionProvider()->getVersion();

        self::assertMatchesRegularExpression('/^(?:dev-[^@]+@[0-9a-f]{7}|(?!dev-).+)$/', $version);
    }
}
