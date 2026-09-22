<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\ValueObject;

final readonly class SiteInfo
{
    public function __construct(
        private string $name,
        private string $version,
        private ?string $ownerNpub = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getOwnerNpub(): ?string
    {
        return $this->ownerNpub;
    }
}
