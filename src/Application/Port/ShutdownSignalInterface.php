<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

interface ShutdownSignalInterface
{
    public function await(): int;
}
