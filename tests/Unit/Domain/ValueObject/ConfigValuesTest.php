<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\ValueObject\ConfigValues;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConfigValuesTest extends TestCase
{
    public function testReadsARequiredString(): void
    {
        self::assertSame('/tmp/app.sqlite', ConfigValues::fromArray(['database_path' => '/tmp/app.sqlite'])->string('database_path'));
    }

    public function testRejectsAMissingRequiredString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('database_path is required');

        ConfigValues::fromArray([])->string('database_path');
    }

    public function testRejectsAnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('database_path must be a non-empty string, got string');

        ConfigValues::fromArray(['database_path' => ''])->string('database_path');
    }

    public function testRejectsANonStringWhereAStringIsExpected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host must be a non-empty string, got int');

        ConfigValues::fromArray(['host' => 42])->optionalString('host');
    }

    public function testAnAbsentOptionalStringIsNull(): void
    {
        self::assertNull(ConfigValues::fromArray([])->optionalString('log_level'));
    }

    public function testReadsARequiredInteger(): void
    {
        self::assertSame(8080, ConfigValues::fromArray(['port' => 8080])->int('port'));
    }

    public function testRejectsAMissingRequiredInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port is required');

        ConfigValues::fromArray([])->int('port');
    }

    public function testDoesNotCoerceANumericStringToAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port must be an integer, got string');

        ConfigValues::fromArray(['port' => '8080'])->optionalInt('port');
    }

    public function testAnAbsentOptionalIntegerIsNull(): void
    {
        self::assertNull(ConfigValues::fromArray([])->optionalInt('worker_pool_limit'));
    }

    public function testReadsAnOptionalBoolean(): void
    {
        self::assertTrue(ConfigValues::fromArray(['secure_cookies' => true])->optionalBool('secure_cookies'));
    }

    public function testAnAbsentOptionalBooleanIsNull(): void
    {
        self::assertNull(ConfigValues::fromArray([])->optionalBool('secure_cookies'));
    }

    public function testDoesNotCoerceATruthyValueToABoolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secure_cookies must be a boolean, got int');

        ConfigValues::fromArray(['secure_cookies' => 1])->optionalBool('secure_cookies');
    }

    public function testReadsAnOptionalStringListReindexed(): void
    {
        $values = ConfigValues::fromArray(['trusted_proxies' => [3 => '10.0.0.1', 7 => '10.0.0.2']]);

        self::assertSame(['10.0.0.1', '10.0.0.2'], $values->optionalStringList('trusted_proxies'));
    }

    public function testAnAbsentOptionalStringListIsNull(): void
    {
        self::assertNull(ConfigValues::fromArray([])->optionalStringList('trusted_proxies'));
    }

    public function testRejectsAStringListThatIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trusted_proxies must be a list of non-empty strings');

        ConfigValues::fromArray(['trusted_proxies' => '10.0.0.1'])->optionalStringList('trusted_proxies');
    }

    public function testRejectsAStringListHoldingANonStringEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trusted_proxies must be a list of non-empty strings');

        ConfigValues::fromArray(['trusted_proxies' => ['10.0.0.1', 5]])->optionalStringList('trusted_proxies');
    }

    public function testReadsThroughASection(): void
    {
        $values = ConfigValues::fromArray(['limits' => ['max_filters' => 5]]);

        self::assertSame(5, $values->section('limits')->int('max_filters'));
    }

    public function testAnAbsentSectionIsEmpty(): void
    {
        self::assertNull(ConfigValues::fromArray([])->section('limits')->optionalInt('max_filters'));
    }

    public function testRejectsASectionThatIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limits must be an array, got int');

        ConfigValues::fromArray(['limits' => 5])->section('limits');
    }

    public function testNamesASectionKeyByItsFullPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limits.max_filters must be an integer, got string');

        ConfigValues::fromArray(['limits' => ['max_filters' => 'five']])->section('limits')->optionalInt('max_filters');
    }

    public function testNamesAKeyInANestedSectionByItsFullPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limits.burst.size must be an integer, got string');

        ConfigValues::fromArray(['limits' => ['burst' => ['size' => 'ten']]])->section('limits')->section('burst')->int('size');
    }

    public function testAcceptsValuesHoldingOnlyKnownKeys(): void
    {
        ConfigValues::fromArray(['host' => '127.0.0.1', 'port' => 8080])->rejectUnknownKeys('host', 'port', 'log_level');

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsAKeyThatIsNotKnown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config key: log_levle');

        ConfigValues::fromArray(['port' => 8080, 'log_levle' => 'debug'])->rejectUnknownKeys('port', 'log_level');
    }

    public function testNamesEveryUnknownKeyAtOnce(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config keys: log_levle, trusted_proxy');

        ConfigValues::fromArray(['log_levle' => 'debug', 'trusted_proxy' => []])->rejectUnknownKeys('log_level');
    }

    public function testNamesAnUnknownKeyInASectionByItsFullPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config key: limits.max_filtres');

        ConfigValues::fromArray(['limits' => ['max_filtres' => 5]])->section('limits')->rejectUnknownKeys('max_filters');
    }

    public function testASectionPrefixCanOnlyComeFromReadingASection(): void
    {
        self::assertTrue(new ReflectionClass(ConfigValues::class)->getConstructor()?->isPrivate());
    }
}
