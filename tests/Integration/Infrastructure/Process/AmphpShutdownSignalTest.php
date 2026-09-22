<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Process;

use Innis\Hubstr\Core\Infrastructure\Process\AmphpShutdownSignal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

final class AmphpShutdownSignalTest extends TestCase
{
    #[DataProvider('shutdownSignals')]
    public function testAwaitReturnsTheShutdownSignalTheProcessReceives(int $signal): void
    {
        EventLoop::delay(0.01, static function () use ($signal): void {
            posix_kill(posix_getpid(), $signal);
        });

        self::assertSame($signal, new AmphpShutdownSignal()->await());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function shutdownSignals(): iterable
    {
        yield 'interrupt' => [SIGINT];
        yield 'terminate' => [SIGTERM];
    }
}
