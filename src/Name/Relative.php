<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser\Name;

use Typhoon\PHPStanTypeParser\Name;

final readonly class Relative
{
    public function __construct(
        private Name $name,
    ) {}

    public function resolveClass(?Name $namespace): Name
    {
        if ($namespace === null) {
            return $this->name;
        }

        return Name::join($namespace, $this->name);
    }

    public function toString(): string
    {
        return Name::RELATIVE_NAMESPACE . $this->name->toString();
    }
}
