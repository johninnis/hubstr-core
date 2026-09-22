<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Process;

use Innis\Hubstr\Core\Application\Port\ShutdownSignalInterface;
use Override;

use function Amp\trapSignal;

final readonly class AmphpShutdownSignal implements ShutdownSignalInterface
{
    private const array SIGNALS = [SIGINT, SIGTERM];

    #[Override]
    public function await(): int
    {
        return trapSignal(self::SIGNALS);
    }
}
