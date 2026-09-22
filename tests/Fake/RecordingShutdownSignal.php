<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Fake;

use Innis\Hubstr\Core\Application\Port\ShutdownSignalInterface;
use Override;

final class RecordingShutdownSignal implements ShutdownSignalInterface
{
    public function __construct(
        private readonly CallLog $log,
        private readonly int $signal = 15,
    ) {
    }

    #[Override]
    public function await(): int
    {
        $this->log->record('await');

        return $this->signal;
    }
}
