<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

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

    public function resolveAsClass(string $name): Name
    {
        return Name::parse($name)->resolveClass($this->namespace, $this->importTable);
    }

    public function resolve(string $name): TemplateT|Name
    {
        return $this->templates[$name] ?? $this->resolveAsClass($name);
    }
}
