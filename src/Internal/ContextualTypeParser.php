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
 * @psalm-internal Typhoon\PHPStanTypeParser
 */
final class ContextualTypeParser
{
    /**
     * @var ?non-empty-array<non-empty-string, Type|\Closure(list<TypeNode>): Type>
     */
    private static ?array $identifierMap = null;

    public function __construct(
        private readonly CustomTypeParser $customTypeParser,
        private readonly TypeContext $context,
    ) {}

    public function parseTypeNode(TypeNode $node): Type
    {
        return match (true) {
            $node instanceof NullableTypeNode => nullOrT($this->parseTypeNode($node->type)),
            $node instanceof ConstTypeNode => $this->parseConstExpr($node->constExpr),
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
                is_numeric($node->value) => intT($node->value),
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
        self::$identifierMap ??= [
            'never' => neverT,
            'void' => voidT,
            'null' => nullT,
            'false' => falseT,
            'true' => trueT,
            'bool' => boolT,
            'boolean' => boolT,
            'int' => self::parseInt(...),
            'integer' => self::parseInt(...),
            'positive-int' => positiveIntT,
            'negative-int' => negativeIntT,
            'non-negative-int' => nonNegativeIntT,
            'non-positive-int' => nonPositiveIntT,
            'non-zero-int' => orT(negativeIntT, positiveIntT),
            'float' => self::parseFloat(...),
            'string' => stringT,
            'non-empty-string' => nonEmptyStringT,
            'resource' => resourceT,
            'array-key' => arrayKeyT,
            'scalar' => scalarT,
            'mixed' => mixedT,
        ];

        $type = self::$identifierMap[$name] ?? null;

        if ($type instanceof Type) {
            if ($genericNodes !== []) {
                throw new \LogicException();
            }

            return $type;
        }

        if ($type instanceof \Closure) {
            return $type($genericNodes);
        }

        $typeArguments = array_map($this->parseTypeNode(...), $genericNodes);
        $customType = $this->customTypeParser->parseCustomType($name, $typeArguments, $this->context);

        if ($customType !== null) {
            return $customType;
        }

        throw new \LogicException(\sprintf('Unsupported identifier `%s`', $name));
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private static function parseInt(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => intT,
            2 => intRangeT(
                min: self::parseRangeLimit($genericNodes[0], 'min', float: false),
                max: self::parseRangeLimit($genericNodes[1], 'max', float: false),
            ),
            default => throw new \LogicException(\sprintf(
                'Int range type should have 2 type arguments, got %d',
                \count($genericNodes),
            ))
        };
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private static function parseFloat(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => floatT,
            2 => floatRangeT(
                min: self::parseRangeLimit($genericNodes[0], 'min', float: true),
                max: self::parseRangeLimit($genericNodes[1], 'max', float: true),
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
    private static function parseRangeLimit(TypeNode $type, string $name, bool $float): ?string
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
}
