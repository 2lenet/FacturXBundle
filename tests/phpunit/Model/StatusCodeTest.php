<?php

namespace Lle\FacturXBundle\Tests\Model;

use Lle\FacturXBundle\Model\StatusCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/** Statuses of the reform plus the ones specific to a platform: labels and final statuses. */
class StatusCodeTest extends TestCase
{
    public function testEachStatusHasItsLabelInBothLanguages(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $labels = Yaml::parseFile(__DIR__ . '/../../../src/Resources/translations/LleFacturXBundle.' . $locale . '.yaml');
            foreach (StatusCode::cases() as $code) {
                $this->assertNotEmpty($labels['facturx']['status'][$code->value] ?? null, $locale . ' ' . $code->value);
            }
        }
    }

    public function testFinalStatuses(): void
    {
        $this->assertSame(['REFUSED', 'PAID', 'REJECTED', 'DUPLICATED'], StatusCode::finalValues());
    }
}
