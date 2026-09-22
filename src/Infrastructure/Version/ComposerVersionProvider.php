<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Version;

use Composer\InstalledVersions;
use Innis\Hubstr\Core\Application\Port\VersionProviderInterface;
use Innis\Hubstr\Core\Domain\ValueObject\PackageVersion;
use Override;

final readonly class ComposerVersionProvider implements VersionProviderInterface
{
    #[Override]
    public function getVersion(): string
    {
        $rootPackage = InstalledVersions::getRootPackage();

        return new PackageVersion($rootPackage['pretty_version'], $rootPackage['reference'])->toString();
    }
}
