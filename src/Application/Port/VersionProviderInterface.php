<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

interface VersionProviderInterface
{
    public function getVersion(): string;
}
