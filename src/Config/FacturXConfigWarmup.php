<?php

namespace Lle\FacturXBundle\Config;

use Lle\ConfigBundle\Contracts\WarmupInterface;
use Lle\ConfigBundle\Repository\AbstractConfigRepository;

/**
 * Creates the empty Factur-X lines of the config screen (bin/console lle:config:warmup), to fill before any sending.
 * A value already set is never overwritten.
 */
class FacturXConfigWarmup implements WarmupInterface
{
    public function warmup(AbstractConfigRepository $configRepository): void
    {
        foreach (FacturXConfig::KEYS as $key) {
            $configRepository->initString(FacturXConfig::GROUP, $key, '');
        }
        foreach (array_keys(FacturXConfig::NOTES) as $key) {
            $configRepository->initText(FacturXConfig::GROUP, $key, '');
        }
    }
}
