<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\Type;

/**
 * @api
 */
interface CustomTypeParser
{
    /**
     * @param non-empty-string $unresolvedName
     * @param list<Type> $typeArguments
     */
    public function parseCustomType(string $unresolvedName, array $typeArguments, TypeContext $context): ?Type;
}
