<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\Type;
use function Typhoon\Type\namedObjectT;

/**
 * @api
 */
final readonly class RuntimeTypeContext implements TypeContext
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
        if (class_exists($unresolvedName) || interface_exists($unresolvedName)) {
            return $unresolvedName;
        }

        throw new \LogicException(\sprintf('Class `%s` does not exist', $unresolvedName));
    }

    public function resolveNameAsType(string $unresolvedName, array $templateArguments = []): Type
    {
        return namedObjectT($this->resolveClassName($unresolvedName), $templateArguments);
    }
}
