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
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\OffsetAccessTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Typhoon\PHPStanTypeParser\Context;
use Typhoon\PHPStanTypeParser\CustomParser;
use Typhoon\Type\ArrayDefaultT;
use Typhoon\Type\ArrayT;
use Typhoon\Type\IterableDefaultT;
use Typhoon\Type\IterableT;
use Typhoon\Type\ListT;
use Typhoon\Type\Type;
use function Typhoon\Type\andT;
use function Typhoon\Type\arrayT;
use function Typhoon\Type\classConstantMaskT;
use function Typhoon\Type\classConstantT;
use function Typhoon\Type\constantT;
use function Typhoon\Type\floatRangeT;
use function Typhoon\Type\floatT;
use function Typhoon\Type\intRangeT;
use function Typhoon\Type\intT;
use function Typhoon\Type\nullOrT;
use function Typhoon\Type\offsetT;
use function Typhoon\Type\orT;
use function Typhoon\Type\stringT;
use const Typhoon\Type\arrayKeyT;
use const Typhoon\Type\arrayT;
use const Typhoon\Type\boolT;
use const Typhoon\Type\callableT;
use const Typhoon\Type\closureT;
use const Typhoon\Type\falseT;
use const Typhoon\Type\floatT;
use const Typhoon\Type\intT;
use const Typhoon\Type\iterableT;
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
final readonly class ContextualParser
{
    public function __construct(
        private CustomParser $customTypeParser,
        private Context $context,
    ) {}

    public function parse(TypeNode $node): Type
    {
        return $this->customTypeParser->parse($node, $this->parse(...), $this->context)
            ?? match (true) {
                $node instanceof NullableTypeNode => nullOrT($this->parse($node->type)),
                $node instanceof ConstTypeNode => $this->parseConstExpr($node->constExpr),
                $node instanceof IdentifierTypeNode => $this->identifier($node->name),
                $node instanceof GenericTypeNode => $this->identifier($node->type->name, $node->genericTypes),
                $node instanceof UnionTypeNode => orT(...array_map($this->parse(...), $node->types)),
                $node instanceof IntersectionTypeNode => andT(...array_map($this->parse(...), $node->types)),
                $node instanceof ArrayTypeNode => arrayT(value: $this->parse($node->type)),
                $node instanceof OffsetAccessTypeNode => offsetT($this->parse($node->type), $this->parse($node->offset)),
                default => throw new \LogicException(\sprintf('`%s` is not supported', $node::class)),
            };
    }

    private function parseConstExpr(ConstExprNode $node): Type
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
            $node instanceof ConstFetchNode => $this->constantFetch($node),
            default => throw new \LogicException(\sprintf('PhpDoc node %s is not supported', $node::class)),
        };
    }

    private function constantFetch(ConstFetchNode $node): Type
    {
        if ($node->className === '') {
            return constantT($node->name);
        }

        $class = $this->context->resolveClassName($node->className);

        if ($node->name === 'class') {
            return stringT($class);
        }

        if (str_contains($node->name, '*')) {
            return classConstantMaskT($class, $node->name);
        }

        return classConstantT($class, $node->name);
    }

    /**
     * @param non-empty-string $name
     * @param list<TypeNode> $genericNodes
     */
    private function identifier(string $name, array $genericNodes = []): Type
    {
        $singleton = match ($name) {
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
            'Closure' => closureT,
            'callable' => callableT,
            'mixed' => mixedT,
            default => null,
        };

        if ($singleton !== null) {
            if ($genericNodes !== []) {
                throw new \LogicException();
            }

            return $singleton;
        }

        if ($name === 'int' || $name === 'integer') {
            return $this->int($genericNodes);
        }

        if ($name === 'float' || $name === 'double') {
            return $this->float($genericNodes);
        }

        $templateArguments = array_map($this->parse(...), $genericNodes);

        if ($name === 'list' || $name === 'non-empty-list') {
            return $this->list($templateArguments, isNonEmpty: $name === 'non-empty-list');
        }

        if ($name === 'array' || $name === 'non-empty-array') {
            return $this->array($templateArguments, isNonEmpty: $name === 'non-empty-array');
        }

        if ($name === 'iterable') {
            return $this->iterable($templateArguments);
        }

        return $this->context->resolveNameAsType($name, $templateArguments);
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function int(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => intT,
            2 => intRangeT(
                min: self::intRangeLimit($genericNodes[0], 'min'),
                max: self::intRangeLimit($genericNodes[1], 'max'),
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
    private function intRangeLimit(TypeNode $type, string $name): ?int
    {
        $string = (string) $type;

        if ($string === $name) {
            return null;
        }

        if (is_numeric($string) && !str_contains($string, '.')) {
            return (int) $string;
        }

        throw new \LogicException();
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function float(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => floatT,
            2 => floatRangeT(
                min: self::floatRangeLimit($genericNodes[0], 'min'),
                max: self::floatRangeLimit($genericNodes[1], 'max'),
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
    private function floatRangeLimit(TypeNode $type, string $name): ?string
    {
        $string = (string) $type;

        if ($string === $name) {
            return null;
        }

        if (is_numeric($string)) {
            return $string;
        }

        throw new \LogicException();
    }

    /**
     * @param list<Type> $templateArguments
     */
    private function list(array $templateArguments, bool $isNonEmpty = false): ListT
    {
        return match ($number = \count($templateArguments)) {
            0 => new ListT(isNonEmpty: $isNonEmpty),
            1 => new ListT(valueType: $templateArguments[0], isNonEmpty: $isNonEmpty),
            default => throw new \LogicException(\sprintf('list type should have at most 1 type arguments, got %d', $number)),
        };
    }

    /**
     * @param list<Type> $templateArguments
     */
    private function array(array $templateArguments, bool $isNonEmpty = false): ArrayDefaultT|ArrayT
    {
        return match ($number = \count($templateArguments)) {
            0 => $isNonEmpty ? new ArrayT(isNonEmpty: true) : arrayT,
            1 => new ArrayT(valueType: $templateArguments[0], isNonEmpty: $isNonEmpty),
            2 => new ArrayT(keyType: $templateArguments[0], valueType: $templateArguments[1], isNonEmpty: $isNonEmpty),
            default => throw new \LogicException(\sprintf('array type should have at most 2 type arguments, got %d', $number)),
        };
    }

    /**
     * @param list<Type> $templateArguments
     */
    private function iterable(array $templateArguments): IterableDefaultT|IterableT
    {
        return match ($number = \count($templateArguments)) {
            0 => iterableT,
            1 => new IterableT(valueType: $templateArguments[0]),
            2 => new IterableT(keyType: $templateArguments[0], valueType: $templateArguments[1]),
            default => throw new \LogicException(\sprintf('iterable type should have at most 2 type arguments, got %d', $number)),
        };
    }
}
