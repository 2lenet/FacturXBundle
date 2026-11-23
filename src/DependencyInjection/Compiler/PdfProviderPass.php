<?php

namespace Lle\FacturXBundle\DependencyInjection\Compiler;

use Lle\FacturXBundle\Invoice\InvoicePdfProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Aliases InvoicePdfProviderInterface to the service of the project implementing it, when there is only one
 * (otherwise, alias it in the project).
 */
class PdfProviderPass implements CompilerPassInterface
{
    public const TAG = 'lle_facturx.pdf_provider';

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(InvoicePdfProviderInterface::class)) {
            return;
        }
        $providers = array_keys($container->findTaggedServiceIds(self::TAG));
        if (count($providers) === 1) {
            $container->setAlias(InvoicePdfProviderInterface::class, $providers[0]);
        }
    }
}
