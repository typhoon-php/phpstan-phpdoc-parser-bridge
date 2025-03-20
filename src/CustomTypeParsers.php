<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\Type;

/**
 * @api
 */
final class CustomTypeParsers implements CustomTypeParser
{
    /**
     * @param iterable<CustomTypeParser> $customTypeParsers
     */
    public function __construct(
        private readonly iterable $customTypeParsers = [],
    ) {}

    public function parseCustomType(string $unresolvedName, array $typeArguments, TypeContext $context): ?Type
    {
        foreach ($this->customTypeParsers as $customTypeParser) {
            $type = $customTypeParser->parseCustomType($unresolvedName, $typeArguments, $context);

            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }
}
