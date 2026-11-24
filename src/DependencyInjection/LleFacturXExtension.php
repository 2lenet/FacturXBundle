<?php

namespace Lle\FacturXBundle\DependencyInjection;

use Lle\FacturXBundle\DependencyInjection\Compiler\PdfProviderPass;
use Lle\FacturXBundle\Invoice\InvoicePdfProviderInterface;
use Lle\FacturXBundle\Platform\Iopole\IopolePlatform;
use Lle\FacturXBundle\Platform\PlatformInterface;
use Lle\PdfGeneratorBundle\Converter\PdfToPdfArchiveConverter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LleFacturXExtension extends Extension
{
    public const ALIAS = 'lle_facturx';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->registerForAutoconfiguration(InvoicePdfProviderInterface::class)->addTag(PdfProviderPass::TAG);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // approved platform: the built-in Iopole one, or a service of the project
        $platform = $config['platform'];
        if ($platform !== null && $platform !== '') {
            $loader->load('services_platform.yaml');
            if ($platform === Configuration::PLATFORM_IOPOLE) {
                $platform = IopolePlatform::class;
            } else {
                $container->removeDefinition(IopolePlatform::class);
            }
            $container->setAlias(PlatformInterface::class, $platform);
        }

        // Factur-X PDF/A-3: only when pdf-generator-bundle is installed (optional dependency)
        if (class_exists(PdfToPdfArchiveConverter::class)) {
            $loader->load('services_pdf.yaml');
        }

        // seller, billing framework and notes in the config screen: needs config-bundle
        $configScreen = $config['config_screen'];
        if ($configScreen && !self::hasBundle($container, 'LleConfigBundle')) {
            throw new InvalidConfigurationException('lle_facturx.config_screen needs 2lenet/config-bundle (LleConfigBundle)');
        }
        if ($configScreen) {
            $loader->load('services_config.yaml');
        }
    }

    public static function hasBundle(ContainerBuilder $container, string $name): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return isset($bundles[$name]);
    }
}
