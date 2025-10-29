<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Typhoon\Type;

/**
 * @api
 */
final readonly class CustomParsers implements CustomParser
{
    /**
     * @param iterable<CustomParser> $customTypeParsers
     */
    public function __construct(
        private iterable $customTypeParsers = [],
    ) {}

    public function parse(TypeNode $node, callable $parse, Context $context): ?Type
    {
        foreach ($this->customTypeParsers as $customTypeParser) {
            $type = $customTypeParser->parse($node, $parse, $context);

            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }
}
