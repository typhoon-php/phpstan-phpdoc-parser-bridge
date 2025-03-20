<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Typhoon\PHPStanTypeParser\Internal\ContextualTypeParser;
use Typhoon\Type\Type;

/**
 * @api
 */
final class PHPStanTypeParser
{
    private readonly CustomTypeParser $customTypeParser;

    /**
     * @param iterable<CustomTypeParser> $customTypeParsers
     */
    public function __construct(
        iterable $customTypeParsers = [],
        private readonly Lexer $lexer = new Lexer(new ParserConfig([])),
        private readonly TypeParser $typeParser = new TypeParser(
            new ParserConfig([]),
            new ConstExprParser(new ParserConfig([])),
        ),
    ) {
        $this->customTypeParser = new CustomTypeParsers($customTypeParsers);
    }

    public function parseString(string $type, TypeContext $context = new RuntimeTypeContext()): Type
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));
        $typeNode = $this->typeParser->parse($tokens);

        return $this->parseTypeNode($typeNode, $context);
    }

    public function parseTypeNode(TypeNode $node, TypeContext $context = new RuntimeTypeContext()): Type
    {
        return (new ContextualTypeParser($this->customTypeParser, $context))->parseTypeNode($node);
    }
}
