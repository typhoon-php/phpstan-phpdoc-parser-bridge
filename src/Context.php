<?php

declare(strict_types=1);

namespace Typhoon\PHPStanTypeParser;

use Typhoon\Type\Type;

/**
 * @api
 */
interface Context
{
    /**
     * @param non-empty-string $unresolvedName
     * @return array{non-empty-string, ?non-empty-string}
     */
    public function resolveConstantName(string $unresolvedName): array;

    /**
     * @param non-empty-string $unresolvedName
     * @return class-string
     */
    public function resolveClassName(string $unresolvedName): string;

    /**
     * @param non-empty-string $unresolvedName
     * @param list<Type> $templateArguments
     */
    public function resolveNameAsType(string $unresolvedName, array $templateArguments = []): Type;
}
