<?php

namespace Lle\FacturXBundle;

use Lle\FacturXBundle\DependencyInjection\Compiler\PdfProviderPass;
use Lle\FacturXBundle\DependencyInjection\LleFacturXExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LleFacturXBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PdfProviderPass());
    }

    /** Config root "lle_facturx" (the default one, from the class name, would be "lle_factur_x"). */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new LleFacturXExtension();
    }
}
