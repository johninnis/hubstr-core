<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Http;

use Innis\Hubstr\Core\Application\Port\SiteInfoProviderInterface;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Override;

final readonly class StaticSiteInfoProvider implements SiteInfoProviderInterface
{
    public function __construct(
        private SiteInfo $siteInfo,
    ) {
    }

    #[Override]
    public function getSiteInfo(): SiteInfo
    {
        return $this->siteInfo;
    }
}
