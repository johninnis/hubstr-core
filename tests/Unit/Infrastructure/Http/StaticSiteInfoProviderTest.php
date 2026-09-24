<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Http;

use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use PHPUnit\Framework\TestCase;

final class StaticSiteInfoProviderTest extends TestCase
{
    public function testAnswersWithTheValueItWasGiven(): void
    {
        $siteInfo = new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner');

        self::assertSame($siteInfo, new StaticSiteInfoProvider($siteInfo)->getSiteInfo());
    }
}
