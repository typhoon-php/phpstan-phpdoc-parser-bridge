<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\NamedObjectT;
use Typhoon\Type\Type;

/**
 * @api
 */
final class RuntimeTypeContext implements TypeContext
{
    public function resolveConstantName(string $unresolvedName): array
    {
        if (\defined($unresolvedName)) {
            return [$unresolvedName, null];
        }

        throw new \LogicException(\sprintf('Constant `%s` is not defined', $unresolvedName));
    }

    public function resolveClassName(string $unresolvedName): string
    {
        if (class_exists($unresolvedName)) {
            return $unresolvedName;
        }

        throw new \LogicException(\sprintf('Class `%s` does not exist', $unresolvedName));
    }

    public function resolveNameAsType(string $unresolvedName, array $typeArguments = []): Type
    {
        /**
         * @todo requires objectT() type constructor
         * @psalm-suppress InternalMethod
         */
        return new NamedObjectT($this->resolveClassName($unresolvedName), $typeArguments);
    }
}
