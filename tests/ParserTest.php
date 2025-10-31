<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Typhoon\PHPStanTypeParser\Internal\ContextualParser;
use Typhoon\Type;
use function Typhoon\Type\andT;
use function Typhoon\Type\arrayT;
use function Typhoon\Type\callableT;
use function Typhoon\Type\classConstantMaskT;
use function Typhoon\Type\classConstantT;
use function Typhoon\Type\constantT;
use function Typhoon\Type\floatRangeT;
use function Typhoon\Type\floatT;
use function Typhoon\Type\intRangeT;
use function Typhoon\Type\intT;
use function Typhoon\Type\iterableT;
use function Typhoon\Type\listT;
use function Typhoon\Type\namedObjectT;
use function Typhoon\Type\nonEmptyArrayT;
use function Typhoon\Type\nonEmptyListT;
use function Typhoon\Type\nullOrT;
use function Typhoon\Type\offsetT;
use function Typhoon\Type\orT;
use function Typhoon\Type\stringT;
use function Typhoon\Type\template;
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
use const Typhoon\Type\nonFalsyStringT;
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

#[CoversClass(Parser::class)]
#[CoversClass(ContextualParser::class)]
final class ParserTest extends TestCase
{
    /**
     * @return \Generator<non-empty-string, Type>
     */
    private static function cases(): \Generator
    {
        yield 'never' => neverT;
        yield 'void' => voidT;
        yield 'null' => nullT;
        yield 'false' => falseT;
        yield 'true' => trueT;
        yield 'bool' => boolT;
        yield 'boolean' => boolT;
        yield 'int' => intT;
        yield 'integer' => intT;
        yield '?int' => nullOrT(intT);
        yield 'positive-int' => positiveIntT;
        yield 'negative-int' => negativeIntT;
        yield 'non-positive-int' => nonPositiveIntT;
        yield 'non-negative-int' => nonNegativeIntT;
        yield 'non-zero-int' => nonZeroIntT;
        yield 'int<0, 1>' => intRangeT(0, 1);
        yield 'int<-10, -23>' => intRangeT(-10, -23);
        yield 'int<min, 123>' => intRangeT(max: 123);
        yield 'int<-99, max>' => intRangeT(min: -99);
        yield 'int<min, max>' => intRangeT();
        yield '0' => intT(0);
        yield '932' => intT(932);
        yield '-5' => intT(-5);
        yield '0.5' => floatT(0.5);
        yield '-4.67' => floatT(-4.67);
        yield 'float' => floatT;
        yield 'float<10.0002, 231.00002>' => floatRangeT(10.0002, 231.00002);
        yield 'float<min, 123>' => floatRangeT(max: 123);
        yield 'float<-99, max>' => floatRangeT(min: -99);
        yield '"0"' => stringT('0');
        yield "'0'" => stringT('0');
        yield '"str"' => stringT('str');
        yield "'str'" => stringT('str');
        yield "'\\n'" => stringT('\n');
        yield 'non-empty-string' => nonEmptyStringT;
        yield 'numeric-string' => numericStringT;
        yield 'lowercase-string' => lowercaseStringT;
        yield 'literal-string' => literalStringT;
        yield 'truthy-string' => truthyStringT;
        yield 'non-falsy-string' => nonFalsyStringT;
        yield 'string' => stringT;
        yield 'const<PHP_INT_MIN>' => constantT('PHP_INT_MIN');
        yield 'stdClass::class' => stringT(\stdClass::class);
        yield 'stdClass::ABC' => classConstantT(\stdClass::class, 'ABC');
        yield 'stdClass::ABC_*' => classConstantMaskT(\stdClass::class, 'ABC_*');
        yield 'resource' => resourceT;
        yield 'array-key' => arrayKeyT;
        yield 'numeric' => numericT;
        yield 'scalar' => scalarT;
        yield 'int|string' => orT(intT, stringT);
        yield '(int|string)|float' => orT(orT(intT, stringT), floatT);
        yield 'int&string' => andT(intT, stringT);
        yield '(int&string)&float' => andT(andT(intT, stringT), floatT);
        yield 'list' => listT();
        yield 'non-empty-list' => nonEmptyListT();
        yield 'list<string>' => listT(stringT);
        yield 'non-empty-list<string>' => nonEmptyListT(stringT);
        yield 'array' => arrayT;
        yield 'string[]' => arrayT(value: stringT);
        yield 'array<string>' => arrayT(value: stringT);
        yield 'array<int, string>' => arrayT(intT, stringT);
        yield 'array[string]' => offsetT(arrayT, stringT);
        yield 'non-empty-array' => nonEmptyArrayT();
        yield 'non-empty-array<string>' => nonEmptyArrayT(value: stringT);
        yield 'non-empty-array<int, string>' => nonEmptyArrayT(intT, stringT);
        yield 'iterable' => iterableT;
        yield 'iterable<string>' => iterableT(value: stringT);
        yield 'iterable<int, string>' => iterableT(intT, stringT);
        yield 'object' => objectT;
        yield 'callable' => callableT;
        yield 'mixed' => mixedT;
        yield \stdClass::class => namedObjectT(\stdClass::class);
        yield \Closure::class => namedObjectT(\Closure::class);
        yield \Stringable::class => namedObjectT(\Stringable::class);
        yield 'Traversable<int, string>' => namedObjectT(\Traversable::class, [intT, stringT]);
        // todo yield 'stdClass|Iterator&Throwable' https://github.com/phpstan/phpdoc-parser/issues/271
        $T = template('T', upperBound: scalarT, lowerBound: stringT, default: arrayKeyT);
        yield 'callable<T of scalar super string = array-key>(T): ?T' => callableT([$T], [$T->type], nullOrT($T->type));
        $T2 = template('T2');
        $T = template('T', $T2->type);
        yield 'callable<T of T2, T2>(): mixed' => callableT([$T, $T2]);
    }

    private ?Parser $parser = null;

    #[DataProvider('provideCases')]
    public function test(string $string, Type $expectedType): void
    {
        $this->parser ??= new Parser();

        $type = $this->parser->parseString($string);

        self::assertEquals($expectedType, $type);
    }

    /**
     * @return \Generator<non-empty-string, array{non-empty-string, Type}>
     */
    public static function provideCases(): iterable
    {
        foreach (self::cases() as $string => $type) {
            $name = is_numeric($string) ? "`{$string}`" : $string;

            yield $name => [$string, $type];
        }
    }
}
