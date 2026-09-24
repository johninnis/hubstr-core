<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;

interface SiteInfoProviderInterface
{
    public function getSiteInfo(): SiteInfo;
}
