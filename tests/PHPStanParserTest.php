<?php

declare(strict_types=1);

namespace Typhoon\PHPStanPhpDocParserBridge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Typhoon\PHPStanPhpDocParserBridge\Internal\TypeConverter;
use Typhoon\Type\Type;
use function Typhoon\Type\andT;
use function Typhoon\Type\diffT;
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

#[CoversClass(PHPStanParser::class)]
#[CoversClass(TypeConverter::class)]
final class PHPStanParserTest extends TestCase
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
        yield 'non-zero-int' => orT(negativeIntT, positiveIntT);
        yield 'int<0, 1>' => intRangeT(0, 1);
        yield 'int<-10, -23>' => intRangeT(-10, -23);
        yield 'int<min, 123>' => intRangeT(max: 123);
        yield 'int<-99, max>' => intRangeT(min: -99);
        yield 'int<min, max>' => intT;
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
        yield 'string' => stringT;
        yield 'non-empty-string' => nonEmptyStringT;
        yield 'resource' => resourceT;
        yield 'array-key' => arrayKeyT;
        yield 'scalar' => scalarT;
        yield 'int|string' => orT(intT, stringT);
        yield '(int|string)|float' => orT(orT(intT, stringT), floatT);
        yield 'int&string' => andT(intT, stringT);
        yield '(int&string)&float' => andT(andT(intT, stringT), floatT);
        yield 'mixed' => mixedT;
        yield 'diff<string, "">' => diffT(stringT, stringT(''));
    }

    /**
     * @return \Generator<non-empty-string, array{non-empty-string, Type}>
     */
    public static function provider(): \Generator
    {
        foreach (self::cases() as $string => $type) {
            $name = is_numeric($string) ? "`{$string}`" : $string;

            yield $name => [$string, $type];
        }
    }

    private ?PHPStanParser $parser = null;

    #[DataProvider('provider')]
    public function test(string $string, Type $expectedType): void
    {
        $this->parser ??= new PHPStanParser();

        $type = $this->parser->parseType($string);

        self::assertEquals($expectedType, $type);
    }
}
