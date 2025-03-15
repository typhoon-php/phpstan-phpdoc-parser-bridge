<?php

declare(strict_types=1);

namespace Typhoon\PHPStanPhpDocParserBridge;

use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Typhoon\PHPStanPhpDocParserBridge\Internal\TypeConverter;
use Typhoon\Type\Type;

/**
 * @api
 */
final class PHPStanParser
{
    private readonly TypeConverter $typeConverter;

    public function __construct(
        private readonly Lexer $lexer = new Lexer(new ParserConfig([])),
        private readonly TypeParser $typeParser = new TypeParser(
            new ParserConfig([]),
            new ConstExprParser(new ParserConfig([])),
        ),
    ) {
        $this->typeConverter = new TypeConverter();
    }

    public function parseType(string $type): Type
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));
        $typeNode = $this->typeParser->parse($tokens);

        return $this->typeConverter->convert($typeNode);
    }
}
