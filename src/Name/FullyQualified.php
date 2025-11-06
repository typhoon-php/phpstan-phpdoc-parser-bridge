<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser\Name;

use Typhoon\PHPStanTypeParser\Name;

final readonly class FullyQualified
{
    public function __construct(
        private Name $name,
    ) {}

    public function resolveClass(): Name
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        return Name::NAMESPACE_SEPARATOR . $this->name->toString();
    }
}
