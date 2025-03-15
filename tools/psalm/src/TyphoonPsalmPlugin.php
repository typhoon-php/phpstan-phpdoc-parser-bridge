<?php

declare(strict_types=1);

namespace Typhoon\PsalmPlugin;

use PhpParser\Node\Expr\ConstFetch;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Psalm\Type\Atomic;
use Psalm\Type\Union;

/**
 * @api
 */
final class TyphoonPsalmPlugin implements PluginEntryPointInterface, AfterExpressionAnalysisInterface
{
    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        $expr = $event->getExpr();

        if (!$expr instanceof ConstFetch) {
            return null;
        }

        /** @psalm-suppress MixedAssignment */
        $name = $expr->name->getAttribute('resolvedName');

        if (!\is_string($name) || !str_starts_with($name, 'Typhoon\Type\\')) {
            return null;
        }

        /** @var non-empty-string $name */
        if (!\defined($name)) {
            return null;
        }

        $typeProvider = $event->getStatementsSource()->getNodeTypeProvider();
        $typeProvider->setType($expr, new Union([new Atomic\TNamedObject(\constant($name)::class)]));

        return null;
    }

    public function __invoke(RegistrationInterface $registration, ?\SimpleXMLElement $config = null): void
    {
        $registration->registerHooksFromClass(self::class);
    }
}
