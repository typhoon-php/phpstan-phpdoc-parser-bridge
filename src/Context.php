<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\AliasAtClassT;
use Typhoon\Type\AliasAtFunctionT;
use Typhoon\Type\TemplateT;

final class Context
{
    /**
     * @var array<non-empty-lowercase-string, Name>
     */
    private array $importTable = [];

    /**
     * @var array<non-empty-string, TemplateT>
     */
    private array $templates = [];

    /**
     * @var array<non-empty-string, AliasAtFunctionT|AliasAtClassT>
     */
    private array $aliases = [];

    public function __construct(
        public readonly ?Name $namespace = null,
    ) {}

    public function use(Name $name, ?Name $as = null): self
    {
        $context = clone $this;
        $context->importTable[($as ?? $name)->toLowercaseString()] = $name;

        return $context;
    }

    public function useTemplate(TemplateT $template): self
    {
        $context = clone $this;
        $context->templates[$template->name] = $template;

        return $context;
    }

    /**
     * @param ?non-empty-string $as
     */
    public function useAlias(AliasAtFunctionT|AliasAtClassT $alias, ?string $as = null): self
    {
        $context = clone $this;
        $context->aliases[$as ?? $alias->name] = $alias;

        return $context;
    }

    public function resolveAsClass(string $name): Name
    {
        return Name::parse($name)->resolveClass($this->namespace, $this->importTable);
    }

    public function resolve(string $name): TemplateT|AliasAtFunctionT|AliasAtClassT|Name
    {
        return $this->templates[$name] ?? $this->aliases[$name] ?? $this->resolveAsClass($name);
    }
}
