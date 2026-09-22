<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use PHPUnit\Framework\TestCase;

final class SiteInfoTest extends TestCase
{
    public function testExposesNameAndVersion(): void
    {
        $info = new SiteInfo('Hubstr Service', '1.2.3');

        self::assertSame('Hubstr Service', $info->getName());
        self::assertSame('1.2.3', $info->getVersion());
    }

    public function testOwnerNpubDefaultsToNullAndIsExposedWhenGiven(): void
    {
        self::assertNull(new SiteInfo('Hubstr Service', '1.2.3')->getOwnerNpub());
        self::assertSame('npub1owner', new SiteInfo('Hubstr Service', '1.2.3', 'npub1owner')->getOwnerNpub());
    }
}
