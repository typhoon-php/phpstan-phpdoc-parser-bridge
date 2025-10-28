<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser\Internal;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Typhoon\PHPStanTypeParser\CustomTypeParser;
use Typhoon\PHPStanTypeParser\TypeContext;
use Typhoon\Type\ArrayDefaultT;
use Typhoon\Type\ArrayT;
use Typhoon\Type\Type;
use function Typhoon\Type\andT;
use function Typhoon\Type\floatRangeT;
use function Typhoon\Type\floatT;
use function Typhoon\Type\intRangeT;
use function Typhoon\Type\intT;
use function Typhoon\Type\nullOrT;
use function Typhoon\Type\orT;
use function Typhoon\Type\stringT;
use const Typhoon\Type\arrayKeyT;
use const Typhoon\Type\arrayT;
use const Typhoon\Type\boolT;
use const Typhoon\Type\falseT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\literalStringT;
use const Typhoon\Type\lowercaseStringT;
use const Typhoon\Type\mixedT;
use const Typhoon\Type\negativeIntT;
use const Typhoon\Type\neverT;
use const Typhoon\Type\nonEmptyStringT;
use const Typhoon\Type\nonNegativeIntT;
use const Typhoon\Type\nonPositiveIntT;
use const Typhoon\Type\nonZeroIntT;
use const Typhoon\Type\nullT;
use const Typhoon\Type\numericStringT;
use const Typhoon\Type\numericT;
use const Typhoon\Type\objectT;
use const Typhoon\Type\positiveIntT;
use const Typhoon\Type\resourceT;
use const Typhoon\Type\scalarT;
use const Typhoon\Type\stringT;
use const Typhoon\Type\trueT;
use const Typhoon\Type\truthyStringT;
use const Typhoon\Type\voidT;

/**
 * @internal
 * @psalm-internal Typhoon\PHPStanTypeParser
 */
final readonly class ContextualTypeParser
{
    public function __construct(
        private CustomTypeParser $customTypeParser,
        private TypeContext $context,
    ) {}

    public function parseTypeNode(TypeNode $node): Type
    {
        return match (true) {
            $node instanceof NullableTypeNode => nullOrT($this->parseTypeNode($node->type)),
            $node instanceof ConstTypeNode => self::parseConstExpr($node->constExpr),
            $node instanceof IdentifierTypeNode => $this->parseIdentifier($node->name),
            $node instanceof GenericTypeNode => $this->parseIdentifier($node->type->name, $node->genericTypes),
            $node instanceof UnionTypeNode => orT(...array_map($this->parseTypeNode(...), $node->types)),
            $node instanceof IntersectionTypeNode => andT(...array_map($this->parseTypeNode(...), $node->types)),
            default => throw new \LogicException(\sprintf('`%s` is not supported', $node::class)),
        };
    }

    private static function parseConstExpr(ConstExprNode $node): Type
    {
        return match (true) {
            $node instanceof ConstExprNullNode => nullT,
            $node instanceof ConstExprFalseNode => falseT,
            $node instanceof ConstExprTrueNode => trueT,
            $node instanceof ConstExprIntegerNode => match (true) {
                is_numeric($node->value) => intT((int) $node->value),
                default => throw new \LogicException(),
            },
            $node instanceof ConstExprFloatNode => match (true) {
                is_numeric($node->value) => floatT($node->value),
                default => throw new \LogicException(),
            },
            $node instanceof ConstExprStringNode => stringT($node->value),
            default => throw new \LogicException(\sprintf('PhpDoc node %s is not supported', $node::class)),
        };
    }

    /**
     * @param non-empty-string $name
     * @param list<TypeNode> $genericNodes
     */
    private function parseIdentifier(string $name, array $genericNodes = []): Type
    {
        $atomic = match ($name) {
            'never' => neverT,
            'void' => voidT,
            'null' => nullT,
            'false' => falseT,
            'true' => trueT,
            'bool', 'boolean' => boolT,
            'positive-int' => positiveIntT,
            'negative-int' => negativeIntT,
            'non-negative-int' => nonNegativeIntT,
            'non-positive-int' => nonPositiveIntT,
            'non-zero-int' => nonZeroIntT,
            'non-empty-string' => nonEmptyStringT,
            'lowercase-string' => lowercaseStringT,
            'numeric-string' => numericStringT,
            'literal-string' => literalStringT,
            'string' => stringT,
            'truthy-string', 'non-falsy-string' => truthyStringT,
            'resource' => resourceT,
            'array-key' => arrayKeyT,
            'numeric' => numericT,
            'scalar' => scalarT,
            'object' => objectT,
            'mixed' => mixedT,
            default => null,
        };

        if ($atomic !== null) {
            if ($genericNodes !== []) {
                throw new \LogicException();
            }

            return $atomic;
        }

        if ($name === 'int' || $name === 'integer') {
            return $this->parseInt($genericNodes);
        }

        if ($name === 'float') {
            return $this->parseFloat($genericNodes);
        }

        $templateArguments = array_map($this->parseTypeNode(...), $genericNodes);

        if ($name === 'array' || $name === 'non-empty-array') {
            return $this->parseArray($templateArguments, isNonEmpty: $name === 'non-empty-array');
        }

        return $this->customTypeParser->parseCustomType($name, $templateArguments, $this->context)
            ?? $this->context->resolveNameAsType($name, $templateArguments);
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function parseInt(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => intT,
            2 => intRangeT(
                min: self::parseIntRangeLimit($genericNodes[0], 'min'),
                max: self::parseIntRangeLimit($genericNodes[1], 'max'),
            ),
            default => throw new \LogicException(\sprintf(
                'Int range type should have 2 type arguments, got %d',
                \count($genericNodes),
            ))
        };
    }

    /**
     * @param 'min'|'max' $name
     */
    private function parseIntRangeLimit(TypeNode $type, string $name): ?int
    {
        if ($type instanceof IdentifierTypeNode) {
            if ($type->name === $name) {
                return null;
            }

            throw new \LogicException();
        }

        if (!$type instanceof ConstTypeNode) {
            throw new \LogicException();
        }

        $expr = $type->constExpr;

        if ($expr instanceof ConstExprIntegerNode && is_numeric($expr->value)) {
            return (int) $expr->value;
        }

        throw new \LogicException();
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function parseFloat(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => floatT,
            2 => floatRangeT(
                min: self::parseFloatRangeLimit($genericNodes[0], 'min'),
                max: self::parseFloatRangeLimit($genericNodes[1], 'max'),
            ),
            default => throw new \LogicException(\sprintf(
                'Float range type should have 2 type arguments, got %d',
                \count($genericNodes),
            ))
        };
    }

    /**
     * @param 'min'|'max' $name
     * @return ?numeric-string
     */
    private function parseFloatRangeLimit(TypeNode $type, string $name): ?string
    {
        if ($type instanceof IdentifierTypeNode) {
            if ($type->name === $name) {
                return null;
            }

            throw new \LogicException();
        }

        if (!$type instanceof ConstTypeNode) {
            throw new \LogicException();
        }

        $expr = $type->constExpr;

        if (($expr instanceof ConstExprFloatNode || $expr instanceof ConstExprIntegerNode) && is_numeric($expr->value)) {
            return $expr->value;
        }

        throw new \LogicException();
    }

    /**
     * @param list<Type> $templateArguments
     */
    private function parseArray(array $templateArguments, bool $isNonEmpty = false): ArrayDefaultT|ArrayT
    {
        return match ($number = \count($templateArguments)) {
            0 => $isNonEmpty ? new ArrayT(isNonEmpty: true) : arrayT,
            1 => new ArrayT(valueType: $templateArguments[0], isNonEmpty: $isNonEmpty),
            2 => new ArrayT(keyType: $templateArguments[0], valueType: $templateArguments[1], isNonEmpty: $isNonEmpty),
            default => throw new \LogicException(\sprintf('array type should have at most 2 type arguments, got %d', $number)),
        };
    }
}
