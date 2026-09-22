<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Application\Service;

use Innis\Hubstr\Core\Application\Service\Kernel;
use Innis\Hubstr\Core\Tests\Fake\CallLog;
use Innis\Hubstr\Core\Tests\Fake\RecordingLifecycle;
use Innis\Hubstr\Core\Tests\Fake\RecordingLogger;
use Innis\Hubstr\Core\Tests\Fake\RecordingServer;
use Innis\Hubstr\Core\Tests\Fake\RecordingShutdownSignal;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class KernelTest extends TestCase
{
    public function testStartsServerWaitsForSignalThenStops(): void
    {
        $log = new CallLog();

        new Kernel(new NullLogger(), new RecordingShutdownSignal($log))->run(new RecordingServer($log), 'started');

        self::assertSame(['server.start', 'await', 'server.stop'], $log->calls);
    }

    public function testStartsTheLifecycleOnceTheServerIsListeningAndStopsItOnceTheServerHasStopped(): void
    {
        $log = new CallLog();

        new Kernel(new NullLogger(), new RecordingShutdownSignal($log), new RecordingLifecycle($log))->run(new RecordingServer($log), 'started');

        self::assertSame(
            ['server.start', 'lifecycle.start', 'await', 'server.stop', 'lifecycle.drain', 'lifecycle.stop'],
            $log->calls,
        );
    }

    public function testLogsBannerOnStartupThenTheShutdownSignalThenCompletion(): void
    {
        $logger = new RecordingLogger();
        $log = new CallLog();

        new Kernel($logger, new RecordingShutdownSignal($log, signal: 15))->run(new RecordingServer($log), 'started', ['port' => 8080]);

        self::assertSame(
            [
                ['message' => 'started', 'context' => ['port' => 8080]],
                ['message' => 'Received shutdown signal', 'context' => ['signal' => 15]],
                ['message' => 'Shutdown complete', 'context' => []],
            ],
            $logger->records,
        );
    }

    public function testStopsTheLifecycleEvenWhenStoppingTheServerFails(): void
    {
        $log = new CallLog();
        $server = new RecordingServer($log, failures: ['stop' => new RuntimeException('stop failed')]);

        try {
            new Kernel(new NullLogger(), new RecordingShutdownSignal($log), new RecordingLifecycle($log))->run($server, 'started');
            self::fail('the failure to stop the server should propagate');
        } catch (RuntimeException) {
            self::assertSame(
                ['server.start', 'lifecycle.start', 'await', 'server.stop', 'lifecycle.drain', 'lifecycle.stop'],
                $log->calls,
            );
        }
    }

    public function testStopsTheServerAndTheLifecycleWhenTheLifecycleFailsToStart(): void
    {
        $log = new CallLog();
        $lifecycle = new RecordingLifecycle($log, new RuntimeException('start failed'));

        try {
            new Kernel(new NullLogger(), new RecordingShutdownSignal($log), $lifecycle)->run(new RecordingServer($log), 'started');
            self::fail('the failure to start the lifecycle should propagate');
        } catch (RuntimeException) {
            self::assertSame(['server.start', 'lifecycle.start', 'server.stop', 'lifecycle.drain', 'lifecycle.stop'], $log->calls);
        }
    }

    public function testStopsNothingWhenTheServerNeverStarted(): void
    {
        $log = new CallLog();
        $server = new RecordingServer($log, failures: ['start' => new RuntimeException('address in use')]);

        try {
            new Kernel(new NullLogger(), new RecordingShutdownSignal($log), new RecordingLifecycle($log))->run($server, 'started');
            self::fail('the failure to start the server should propagate');
        } catch (RuntimeException) {
            self::assertSame(['server.start'], $log->calls);
        }
    }

    public function testDoesNotReportACompletedShutdownWhenStoppingFailed(): void
    {
        $logger = new RecordingLogger();
        $log = new CallLog();
        $server = new RecordingServer($log, failures: ['stop' => new RuntimeException('stop failed')]);

        try {
            new Kernel($logger, new RecordingShutdownSignal($log))->run($server, 'started');
            self::fail('the failure to stop the server should propagate');
        } catch (RuntimeException) {
            self::assertNotContains('Shutdown complete', array_column($logger->records, 'message'));
        }
    }
}
