<?php

namespace Lle\FacturXBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const PLATFORM_IOPOLE = 'iopole';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lle_facturx');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('platform')
                    ->info('Approved platform the invoices are sent to: "iopole", or the id of your own service implementing PlatformInterface. Empty: no sending. Credentials: FACTURX_PLATFORM_* environment variables only')
                    ->defaultNull()
                ->end()
                ->booleanNode('config_screen')
                    ->info('Seller, billing framework and notes in the config screen of 2lenet/config-bundle (group "facturx", FacturXConfig)')
                    ->defaultFalse()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
