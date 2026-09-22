<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

use Closure;

interface ServerInterface
{
    public function start(): void;

    public function stop(): void;

    /**
     * @param Closure(): void $callback
     */
    public function onStop(Closure $callback): void;
}
