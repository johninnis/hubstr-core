<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\ValueObject\PackageVersion;
use PHPUnit\Framework\TestCase;

final class PackageVersionTest extends TestCase
{
    public function testAReleaseVersionStandsAlone(): void
    {
        self::assertSame('v0.1.0', new PackageVersion('v0.1.0', '73c2de1f0a9b8c7d6e5f')->toString());
    }

    public function testADevelopmentVersionCarriesTheShortCommitReference(): void
    {
        self::assertSame('dev-master@73c2de1', new PackageVersion('dev-master', '73c2de1f0a9b8c7d6e5f')->toString());
    }

    public function testADevelopmentVersionWithNoReferenceStandsAlone(): void
    {
        self::assertSame('dev-master', new PackageVersion('dev-master', null)->toString());
    }

    public function testADevelopmentVersionWithAnEmptyReferenceStandsAlone(): void
    {
        self::assertSame('dev-master', new PackageVersion('dev-master', '')->toString());
    }
}
