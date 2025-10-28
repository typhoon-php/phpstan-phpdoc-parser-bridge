<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Typhoon\Type\Type;

/**
 * @api
 */
interface CustomParser
{
    /**
     * @param callable(TypeNode): Type $parse
     */
    public function parse(TypeNode $node, callable $parse, Context $context): ?Type;
}
