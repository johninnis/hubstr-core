<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Config;

use Innis\Hubstr\Core\Domain\Exception\ConfigFileNotFoundException;
use Innis\Hubstr\Core\Infrastructure\Config\ConfigLoader;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private const string ENVIRONMENT_VARIABLE = 'HUBSTR_CORE_TEST_CONFIG';

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        putenv(self::ENVIRONMENT_VARIABLE);
        $this->directory->remove();
    }

    public function testLoadsTheValuesFromTheDefaultPath(): void
    {
        $path = $this->writeConfig('default.php', ['host' => '0.0.0.0', 'storage_path' => '/tmp/blobs']);

        $values = new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($path);

        self::assertSame('/tmp/blobs', $values->string('storage_path'));
    }

    public function testEnvironmentVariableOverridesDefaultPath(): void
    {
        $override = $this->writeConfig('override.php', ['host' => '10.0.0.9']);
        putenv(self::ENVIRONMENT_VARIABLE.'='.$override);

        $values = new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($this->directory->path('missing.php'));

        self::assertSame('10.0.0.9', $values->string('host'));
    }

    public function testAnEmptyEnvironmentVariableLeavesTheDefaultPathInPlace(): void
    {
        $path = $this->writeConfig('default.php', ['host' => '0.0.0.0']);
        putenv(self::ENVIRONMENT_VARIABLE.'=');

        self::assertSame('0.0.0.0', new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($path)->string('host'));
    }

    public function testAConfigFileCannotSeeTheLoader(): void
    {
        $path = $this->directory->path('scope.php');
        file_put_contents($path, '<?php return ["sees_the_loader" => isset($this)];');

        self::assertFalse(new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($path)->optionalBool('sees_the_loader'));
    }

    public function testAConfigFileCannotChangeWhichFileTheLoaderReports(): void
    {
        $path = $this->directory->path('clobber.php');
        file_put_contents($path, '<?php $path = "/somewhere/else.php"; $defaultPath = $path; return 42;');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file must return an array: '.$path);

        new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($path);
    }

    public function testThrowsWhenConfigFileDoesNotReturnAnArray(): void
    {
        $path = $this->directory->path('scalar.php');
        file_put_contents($path, '<?php return 42;');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file must return an array');

        new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($path);
    }

    public function testThrowsWhenConfigFileMissing(): void
    {
        $this->expectException(ConfigFileNotFoundException::class);

        new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($this->directory->path('missing.php'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeConfig(string $name, array $values): string
    {
        $path = $this->directory->path($name);
        file_put_contents($path, '<?php return '.var_export($values, true).';');

        return $path;
    }
}
