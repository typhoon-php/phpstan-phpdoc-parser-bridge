<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Typhoon\PHPStanTypeParser\Internal\ContextualParser;
use Typhoon\Type;

/**
 * @api
 */
final readonly class Parser
{
    private CustomParser $customTypeParser;

    /**
     * @param iterable<CustomParser> $customTypeParsers
     */
    public function __construct(
        iterable $customTypeParsers = [],
        private Lexer $lexer = new Lexer(new ParserConfig([])),
        private TypeParser $typeParser = new TypeParser(
            new ParserConfig([]),
            new ConstExprParser(new ParserConfig([])),
        ),
    ) {
        $this->customTypeParser = new CustomParsers($customTypeParsers);
    }

    public function parseString(string $type, Context $context = new RuntimeContext()): Type
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));
        $typeNode = $this->typeParser->parse($tokens);

        return $this->parseTypeNode($typeNode, $context);
    }

    public function parseTypeNode(TypeNode $node, Context $context = new RuntimeContext()): Type
    {
        return (new ContextualParser($this->customTypeParser, $context))->parse($node);
    }
}
