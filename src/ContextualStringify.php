<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type;
use Typhoon\Type\AliasAtClassT;
use Typhoon\Type\AliasAtFunctionT;
use Typhoon\Type\AliasT;
use Typhoon\Type\Visitor\Fallback;
use Typhoon\Type\Visitor\Stringify;
use Typhoon\Type\Visitor\WeakVisitor;

/**
 * @extends Fallback<non-empty-string>
 */
final class ContextualStringify extends Fallback
{
    private readonly Stringify $stringify;

    public function __construct(
        private readonly Context $context,
    ) {
        $this->stringify = new Stringify(new WeakVisitor($this));
    }

    /**
     * @return non-empty-string
     */
    public function unsafe(Type $type): string
    {
        $string = $type->accept($this);

        if ($string[0] === '(') {
            /** @phpstan-ignore return.type */
            return substr($string, 1, -1);
        }

        return $string;
    }

    public function aliasAtFunctionT(AliasAtFunctionT $type): string
    {
        return $this->context->nameAlias($type) ?? $type->accept($this->stringify);
    }

    public function aliasAtClassT(AliasAtClassT $type): string
    {
        return $this->context->nameAlias($type) ?? $type->accept($this->stringify);
    }

    public function aliasT(AliasT $type): string
    {
        return $type->alias->accept($this) . $this->stringify->templateArguments($type->templateArguments);
    }

    protected function fallback(Type $type): string
    {
        return $type->accept($this->stringify);
    }
}
