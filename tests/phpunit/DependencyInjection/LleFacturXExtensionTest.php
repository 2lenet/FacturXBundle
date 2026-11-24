<?php

namespace Lle\FacturXBundle\Tests\DependencyInjection;

use Lle\FacturXBundle\Command\RefreshStatusesCommand;
use Lle\FacturXBundle\Command\SendCommand;
use Lle\FacturXBundle\Config\FacturXConfig;
use Lle\FacturXBundle\Config\FacturXConfigWarmup;
use Lle\FacturXBundle\DependencyInjection\LleFacturXExtension;
use Lle\FacturXBundle\Invoice\InvoiceSender;
use Lle\FacturXBundle\Platform\Iopole\IopolePlatform;
use Lle\FacturXBundle\Platform\PlatformInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Platform chosen in the config, config screen only when asked (needs config-bundle). */
class LleFacturXExtensionTest extends TestCase
{
    private const CONFIG_BUNDLE = ['LleConfigBundle' => 'Lle\ConfigBundle\LleConfigBundle'];

    public function testNothingByDefault(): void
    {
        $container = $this->container(self::CONFIG_BUNDLE, []);

        $this->assertFalse($container->has(PlatformInterface::class));
        $this->assertFalse($container->hasDefinition(SendCommand::class));
        $this->assertFalse($container->hasDefinition(FacturXConfig::class));
        $this->assertFalse($container->hasDefinition(InvoiceSender::class));
    }

    public function testIopolePlatform(): void
    {
        $container = $this->container([], ['platform' => 'iopole']);

        $this->assertSame(IopolePlatform::class, (string)$container->getAlias(PlatformInterface::class));
        // credentials read from the environment only
        $iopole = $container->getDefinition(IopolePlatform::class);
        $this->assertSame('%env(FACTURX_PLATFORM_CLIENT_SECRET)%', $iopole->getArgument('$clientSecret'));
        $this->assertSame('%env(FACTURX_PLATFORM_ACCOUNT_ID)%', $iopole->getArgument('$customerId'));
        $this->assertTrue($container->hasDefinition(SendCommand::class));
        $this->assertTrue($container->hasDefinition(InvoiceSender::class));
        $this->assertTrue($container->hasDefinition(RefreshStatusesCommand::class));
    }

    public function testPlatformOfTheProject(): void
    {
        $container = $this->container([], ['platform' => 'app.my_platform']);

        $this->assertSame('app.my_platform', (string)$container->getAlias(PlatformInterface::class));
        $this->assertFalse($container->hasDefinition(IopolePlatform::class));
    }

    public function testConfigScreen(): void
    {
        $container = $this->container(self::CONFIG_BUNDLE, ['config_screen' => true]);
        $this->assertTrue($container->hasDefinition(FacturXConfig::class));
        $this->assertTrue($container->hasDefinition(FacturXConfigWarmup::class));
    }

    public function testConfigScreenWithoutConfigBundle(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->container([], ['config_screen' => true]);
    }

    /**
     * @param array<string, class-string> $bundles
     * @param array<string, mixed> $config
     */
    private function container(array $bundles, array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        (new LleFacturXExtension())->load([$config], $container);

        return $container;
    }
}
