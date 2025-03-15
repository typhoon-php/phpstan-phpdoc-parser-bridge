<?php

declare(strict_types=1);

namespace Typhoon\PHPStanPhpDocParserBridge;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * @api
 */
final class PHPStanParser
{
    public function __construct(
        private readonly ParserConfig $config = new ParserConfig([]),
    ) {}

    public function parseType(string $type): TypeNode
    {
        $tokens = new TokenIterator($this->lexer()->tokenize($type));

        return $this->typeParser()->parse($tokens);
    }

    private ?Lexer $lexer = null;

    private function lexer(): Lexer
    {
        return $this->lexer ??= new Lexer($this->config);
    }

    private ?ConstExprParser $constExprParser = null;

    private function constExprParser(): ConstExprParser
    {
        return $this->constExprParser ??= new ConstExprParser($this->config);
    }

    private ?TypeParser $typeParser = null;

    private function typeParser(): TypeParser
    {
        return $this->typeParser ??= new TypeParser($this->config, $this->constExprParser());
    }
}
