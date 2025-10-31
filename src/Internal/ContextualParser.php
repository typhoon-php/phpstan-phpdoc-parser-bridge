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
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode;
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
use Typhoon\Type;
use Typhoon\Type\ArrayBareT;
use Typhoon\Type\ArrayT;
use Typhoon\Type\CallableT;
use Typhoon\Type\ConstantT;
use Typhoon\Type\IterableBareT;
use Typhoon\Type\IterableT;
use Typhoon\Type\ListT;
use Typhoon\Type\Parameter;
use Typhoon\Type\Template;
use Typhoon\Type\TemplateT;
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
use function Typhoon\Type\param;
use function Typhoon\Type\stringT;
use const Typhoon\Type\arrayKeyT;
use const Typhoon\Type\arrayT;
use const Typhoon\Type\boolT;
use const Typhoon\Type\callableT;
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
final class ContextualParser
{
    /**
     * @var array<non-empty-string, TemplateT>
     */
    private array $templateTypes = [];

    public function __construct(
        private readonly CustomParser $customTypeParser,
        private readonly Context $context,
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
                $node instanceof CallableTypeNode => $this->callable($node),
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
            // todo $node instanceof ConstExprArrayNode => array shape
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

        if ($name === 'const') {
            return $this->const($genericNodes);
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

        return $this->templateTypes[$name] ?? $this->context->resolveNameAsType($name, $templateArguments);
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function int(array $genericNodes): Type
    {
        return match (\count($genericNodes)) {
            0 => intT,
            2 => intRangeT(
                min: self::intRangeLimit($genericNodes[0]),
                max: self::intRangeLimit($genericNodes[1]),
            ),
            default => throw new \LogicException(\sprintf(
                'Int range type should have 2 type arguments, got %d',
                \count($genericNodes),
            ))
        };
    }

    private function intRangeLimit(TypeNode $type): int
    {
        $string = (string) $type;

        return match (true) {
            $string === 'min' => PHP_INT_MIN,
            $string === 'max' => PHP_INT_MAX,
            preg_match('/^-?\d+$/D', $string) === 1 => (int) $string,
            default => throw new \LogicException(),
        };
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
            default => throw new \LogicException(\sprintf('list type should have at most 1 type argument, got %d', $number)),
        };
    }

    /**
     * @param list<Type> $templateArguments
     */
    private function array(array $templateArguments, bool $isNonEmpty = false): ArrayBareT|ArrayT
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
    private function iterable(array $templateArguments): IterableBareT|IterableT
    {
        return match ($number = \count($templateArguments)) {
            0 => iterableT,
            1 => new IterableT(valueType: $templateArguments[0]),
            2 => new IterableT(keyType: $templateArguments[0], valueType: $templateArguments[1]),
            default => throw new \LogicException(\sprintf('iterable type should have at most 2 type arguments, got %d', $number)),
        };
    }

    /**
     * @param list<TypeNode> $genericNodes
     */
    private function const(array $genericNodes): ConstantT
    {
        if (\count($genericNodes) !== 1) {
            throw new \LogicException(\sprintf('const type should have exactly 1 type argument, got %d', \count($genericNodes)));
        }

        $node = $genericNodes[0];

        if (!$node instanceof IdentifierTypeNode) {
            throw new \LogicException();
        }

        return new ConstantT($node->name);
    }

    private function callable(CallableTypeNode $node): CallableT
    {
        return new CallableT(
            templates: array_map(
                // 4. resolve templates
                static fn(\Closure $lazyTemplate): Template => $lazyTemplate(),
                array_map(
                    function (TemplateTagValueNode $node): \Closure {
                        // 2. create template factory
                        $factory = Template::factory(
                            name: $node->name,
                            // 1. assign template type by reference
                            type: $this->templateTypes[$node->name],
                        );

                        // 3. apply the rest of the template's properties to the factory
                        return fn(): Template => $factory(
                            lowerBound: $node->lowerBound === null ? neverT : $this->parse($node->lowerBound),
                            upperBound: $node->bound === null ? mixedT : $this->parse($node->bound),
                            default: $node->default === null ? null : $this->parse($node->default),
                        );
                    },
                    $node->templateTypes,
                ),
            ),
            parameters: array_map(
                fn(CallableTypeParameterNode $node): Parameter => new Parameter(
                    name: $node->parameterName === '' ? null : $node->parameterName,
                    type: $this->parse($node->type),
                    hasDefault: $node->isOptional,
                    isPassedByReference: $node->isReference,
                    isVariadic: $node->isVariadic,
                ),
                $node->parameters,
            ),
            returnType: $this->parse($node->returnType),
        );
    }
}
