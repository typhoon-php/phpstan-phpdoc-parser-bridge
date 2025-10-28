<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\Type;

/**
 * @api
 */
final readonly class CustomTypeParsers implements CustomTypeParser
{
    /**
     * @param iterable<CustomTypeParser> $customTypeParsers
     */
    public function __construct(
        private iterable $customTypeParsers = [],
    ) {}

    public function parseCustomType(string $unresolvedName, array $templateArguments, TypeContext $context): ?Type
    {
        foreach ($this->customTypeParsers as $customTypeParser) {
            $type = $customTypeParser->parseCustomType($unresolvedName, $templateArguments, $context);

            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }
}
