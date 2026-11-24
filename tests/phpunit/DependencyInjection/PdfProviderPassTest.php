<?php

namespace Lle\FacturXBundle\Tests\DependencyInjection;

use Lle\FacturXBundle\DependencyInjection\Compiler\PdfProviderPass;
use Lle\FacturXBundle\Invoice\InvoicePdfProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** The only PDF provider of the project is used by InvoiceSender, without configuration. */
class PdfProviderPassTest extends TestCase
{
    public function testAliasTheOnlyProvider(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.pdf', \stdClass::class)->addTag(PdfProviderPass::TAG);

        (new PdfProviderPass())->process($container);

        $this->assertSame('app.pdf', (string)$container->getAlias(InvoicePdfProviderInterface::class));
    }

    public function testNoAliasWhenSeveralProviders(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.pdf1', \stdClass::class)->addTag(PdfProviderPass::TAG);
        $container->register('app.pdf2', \stdClass::class)->addTag(PdfProviderPass::TAG);

        (new PdfProviderPass())->process($container);

        $this->assertFalse($container->has(InvoicePdfProviderInterface::class));
    }
}
