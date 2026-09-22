<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\ValueObject;

final readonly class PackageVersion
{
    private const string DEVELOPMENT_PREFIX = 'dev-';
    private const int SHORT_REFERENCE_LENGTH = 7;

    public function __construct(
        private string $prettyVersion,
        private ?string $reference,
    ) {
    }

    public function toString(): string
    {
        if (!str_starts_with($this->prettyVersion, self::DEVELOPMENT_PREFIX) || null === $this->reference || '' === $this->reference) {
            return $this->prettyVersion;
        }

        return $this->prettyVersion.'@'.substr($this->reference, 0, self::SHORT_REFERENCE_LENGTH);
    }
}
