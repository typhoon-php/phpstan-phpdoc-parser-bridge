<?php

declare(strict_types=1);

namespace Typhoon\PHPStanPhpDocParserBridge\Internal;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
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
use const Typhoon\Type\boolT;
use const Typhoon\Type\falseT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\mixedT;
use const Typhoon\Type\negativeIntT;
use const Typhoon\Type\neverT;
use const Typhoon\Type\nonEmptyStringT;
use const Typhoon\Type\nonNegativeIntT;
use const Typhoon\Type\nonPositiveIntT;
use const Typhoon\Type\nullT;
use const Typhoon\Type\positiveIntT;
use const Typhoon\Type\resourceT;
use const Typhoon\Type\scalarT;
use const Typhoon\Type\stringT;
use const Typhoon\Type\trueT;
use const Typhoon\Type\voidT;

/**
 * @internal
 * @psalm-internal Typhoon\PHPStanPhpDocParserBridge
 */
final class TypeConverter
{
    public function convert(TypeNode $node): Type
    {
        if ($node instanceof NullableTypeNode) {
            return nullOrT($this->convert($node->type));
        }

        if ($node instanceof ConstTypeNode) {
            return $this->reflectConstExpr($node);
        }

        if ($node instanceof IdentifierTypeNode) {
            return $this->reflectIdentifier($node->name);
        }

        if ($node instanceof GenericTypeNode) {
            return $this->reflectIdentifier($node->type->name, $node->genericTypes);
        }

        if ($node instanceof UnionTypeNode) {
            return orT(...array_map($this->convert(...), $node->types));
        }

        if ($node instanceof IntersectionTypeNode) {
            return andT(...array_map($this->convert(...), $node->types));
        }

        throw new \LogicException(\sprintf('`%s` is not supported', $node::class));
    }

    /**
     * @param non-empty-string $name
     * @param list<TypeNode> $genericNodes
     */
    private function reflectIdentifier(string $name, array $genericNodes = []): Type
    {
        return match ($name) {
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
            'non-zero-int' => orT(negativeIntT, positiveIntT),
            'int', 'integer' => match (\count($genericNodes)) {
                0 => intT,
                2 => intRangeT(
                    min: $this->reflectRangeLimit($genericNodes[0], 'min', float: false),
                    max: $this->reflectRangeLimit($genericNodes[1], 'max', float: false),
                ),
                default => throw new \LogicException(\sprintf(
                    'int range type should have 2 type arguments, got %d',
                    \count($genericNodes),
                ))
            },
            'float' => match (\count($genericNodes)) {
                0 => floatT,
                2 => floatRangeT(
                    min: $this->reflectRangeLimit($genericNodes[0], 'min', float: true),
                    max: $this->reflectRangeLimit($genericNodes[1], 'max', float: true),
                ),
                default => throw new \LogicException(\sprintf(
                    'float range type should have 2 type arguments, got %d',
                    \count($genericNodes),
                ))
            },
            'string' => stringT,
            'non-empty-string' => nonEmptyStringT,
            'resource' => resourceT,
            'array-key' => arrayKeyT,
            'scalar' => scalarT,
            'mixed' => mixedT,
            default => throw new \LogicException(),
        };
    }

    /**
     * @param 'min'|'max' $name
     * @return ?numeric-string
     */
    private function reflectRangeLimit(TypeNode $type, string $name, bool $float): ?string
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

        if (($float && $expr instanceof ConstExprFloatNode) || $expr instanceof ConstExprIntegerNode) {
            if (!is_numeric($expr->value)) {
                throw new \LogicException();
            }

            return $expr->value;
        }

        throw new \LogicException();
    }

    private function reflectConstExpr(ConstTypeNode $node): Type
    {
        $exprNode = $node->constExpr;

        if ($exprNode instanceof ConstExprNullNode) {
            return nullT;
        }

        if ($exprNode instanceof ConstExprTrueNode) {
            return trueT;
        }

        if ($exprNode instanceof ConstExprFalseNode) {
            return falseT;
        }

        if ($exprNode instanceof ConstExprIntegerNode) {
            if (!is_numeric($exprNode->value)) {
                throw new \LogicException();
            }

            return intT($exprNode->value);
        }

        if ($exprNode instanceof ConstExprFloatNode) {
            if (!is_numeric($exprNode->value)) {
                throw new \LogicException();
            }

            return floatT($exprNode->value);
        }

        if ($exprNode instanceof ConstExprStringNode) {
            return stringT($exprNode->value);
        }

        throw new \LogicException(\sprintf('PhpDoc node %s is not supported', $exprNode::class));
    }
}
