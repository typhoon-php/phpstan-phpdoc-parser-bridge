<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Typhoon\Type;

/**
 * @api
 */
interface CustomParser
{
    public function parse(TypeNode $node, Parser $parser): ?Type;
}
