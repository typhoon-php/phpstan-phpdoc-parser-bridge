<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\PHPStanTypeParser\Name\FullyQualified;
use Typhoon\PHPStanTypeParser\Name\Relative;

final class Name
{
    public const NAMESPACE_SEPARATOR = '\\';
    public const RELATIVE_NAMESPACE = 'namespace\\';
    public const SELF = 'self';
    public const PARENT = 'parent';
    public const STATIC = 'static';
    private const REGEXP = '/^(\\\|namespace\\\)?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+(?>\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+)*+)$/D';

    public static function parse(string $name): self|Relative|FullyQualified
    {
        if (preg_match(self::REGEXP, $name, $matches) !== 1) {
            throw new \InvalidArgumentException();
        }

        /** @phpstan-ignore argument.type */
        $name = new self(explode(self::NAMESPACE_SEPARATOR, $matches[2]));

        return match ($matches[1]) {
            '' => $name,
            '\\' => new FullyQualified($name),
            'namespace\\' => new Relative($name),
        };
    }

    public static function join(self $a, self $b): self
    {
        return new self([...$a->parts, ...$a->parts]);
    }

    /**
     * @var ?non-empty-lowercase-string
     */
    private ?string $lowercaseString = null;

    /**
     * @param non-empty-list<non-empty-string> $parts
     */
    private function __construct(
        private readonly array $parts,
    ) {}

    public function isUnqualified(): bool
    {
        return \count($this->parts) === 1;
    }

    public function isQualified(): bool
    {
        return \count($this->parts) > 1;
    }

    public function last(): self
    {
        return new self([$this->parts[array_key_last($this->parts)]]);
    }

    public function isRelativeClass(): bool
    {
        return $this->isSelf() || $this->isParent() || $this->isStatic();
    }

    public function isSelf(): bool
    {
        return $this->toLowercaseString() === self::SELF;
    }

    public function isParent(): bool
    {
        return $this->toLowercaseString() === self::PARENT;
    }

    public function isStatic(): bool
    {
        return $this->toLowercaseString() === self::STATIC;
    }

    /**
     * @param array<non-empty-lowercase-string, Name> $importTable
     */
    public function resolveClass(?self $namespace, array $importTable): self
    {
        if ($this->isRelativeClass()) {
            return $this;
        }

        $import = $importTable[strtolower($this->parts[0])] ?? null;

        if ($import === null) {
            if ($namespace === null) {
                return $this;
            }

            return new self([...$namespace->parts, ...$this->parts]);
        }

        if (\count($this->parts) > 1) {
            return new self([...$import->parts, ...\array_slice($this->parts, 1)]);
        }

        return $import;
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        return implode(self::NAMESPACE_SEPARATOR, $this->parts);
    }

    /**
     * @return non-empty-lowercase-string
     */
    public function toLowercaseString(): string
    {
        return $this->lowercaseString ??= strtolower($this->toString());
    }
}
