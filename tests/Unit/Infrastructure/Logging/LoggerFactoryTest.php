<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Logging;

use Amp\ByteStream\WritableBuffer;
use Innis\Hubstr\Core\Domain\Enum\LogLevel;
use Innis\Hubstr\Core\Infrastructure\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

final class LoggerFactoryTest extends TestCase
{
    public function testWritesARecordAtTheConfiguredLevelToTheSink(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Warning)->create('relay')->warning('disk nearly full');

        self::assertStringContainsString('disk nearly full', self::written($sink));
    }

    public function testDropsARecordBelowTheConfiguredLevel(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Warning)->create('relay')->info('client connected');

        self::assertSame('', self::written($sink));
    }

    public function testNamesTheChannelAndTheLevelOnEachLine(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Debug)->create('blossom')->error('upload failed');

        self::assertStringContainsString('blossom.ERROR: upload failed', self::written($sink));
    }

    public function testCarriesTheContextOnTheLine(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Debug)->create('relay')->info('started', ['port' => 8080]);

        self::assertStringContainsString('{"port":8080}', self::written($sink));
    }

    public function testWritesOneLinePerRecord(): void
    {
        $sink = new WritableBuffer();
        $logger = new LoggerFactory($sink, LogLevel::Debug)->create('relay');

        $logger->info('first');
        $logger->info("second\nwith a line break");

        self::assertSame(2, substr_count(self::written($sink), "\n"));
    }

    public function testEndsALineAtItsMessageWhenTheRecordCarriesNoContext(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Debug)->create('relay')->info('Shutdown complete');

        self::assertStringEndsWith("relay.INFO: Shutdown complete\n", self::written($sink));
    }

    public function testEndsALineAtItsContextWhenTheRecordCarriesSome(): void
    {
        $sink = new WritableBuffer();

        new LoggerFactory($sink, LogLevel::Debug)->create('relay')->info('started', ['port' => 8080]);

        self::assertStringEndsWith("relay.INFO: started {\"port\":8080}\n", self::written($sink));
    }

    private static function written(WritableBuffer $sink): string
    {
        $sink->close();

        return $sink->buffer();
    }
}
