<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Integration\Infrastructure\Filesystem;

use Innis\Hubstr\Core\Domain\Exception\DirectoryCreationException;
use Innis\Hubstr\Core\Infrastructure\Filesystem\Directory;
use Innis\Hubstr\Core\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DirectoryTest extends TestCase
{
    private TemporaryDirectory $root;

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->root->remove();
    }

    public function testCreatesNestedMissingDirectories(): void
    {
        $path = $this->root->path('a/b/c');

        Directory::atPath($path)->ensure();

        self::assertDirectoryExists($path);
    }

    public function testIsIdempotentWhenDirectoryAlreadyExists(): void
    {
        Directory::atPath($this->root->getPath())->ensure();
        Directory::atPath($this->root->getPath())->ensure();

        self::assertDirectoryExists($this->root->getPath());
    }

    public function testFailsWithADomainExceptionWhenThePathCannotBeCreated(): void
    {
        Directory::atPath($this->root->getPath())->ensure();
        file_put_contents($this->root->path('occupied'), '');

        $this->expectException(DirectoryCreationException::class);
        $this->expectExceptionMessage('Failed to create directory '.$this->root->path('occupied/child: mkdir(): Not a directory'));

        Directory::atPath($this->root->path('occupied/child'))->ensure();
    }

    public function testRejectsABlankPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory path must not be blank');

        Directory::atPath('  ');
    }
}
