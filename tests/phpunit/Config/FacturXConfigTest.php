<?php

namespace Lle\FacturXBundle\Tests\Config;

use Doctrine\ORM\EntityManagerInterface;
use Lle\ConfigBundle\Repository\AbstractConfigRepository;
use Lle\FacturXBundle\Config\FacturXConfig;
use Lle\FacturXBundle\Config\FacturXConfigWarmup;
use PHPUnit\Framework\TestCase;

/** Seller, billing framework and notes read from the config screen, lines created by the warm-up. */
class FacturXConfigTest extends TestCase
{
    public function testInvoiceDataFromTheConfigScreen(): void
    {
        $values = [
            FacturXConfig::SELLER_NAME => '2LE',
            FacturXConfig::SELLER_SIREN => '440138717',
            FacturXConfig::BUSINESS_PROCESS => 'S1',
            FacturXConfig::NOTE_PMT => 'Indemnité forfaitaire de 40 €',
            FacturXConfig::NOTE_PMD => ' ',
        ];
        $repository = $this->createMock(AbstractConfigRepository::class);
        $read = fn (string $group, string $key, string $default): string => $values[$key] ?? $default;
        $repository->method('getString')->willReturnCallback($read);
        $repository->method('getText')->willReturnCallback($read);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $data = (new FacturXConfig($em))->getInvoiceData();

        $this->assertSame('S1', $data['business_process']);
        $this->assertSame([['content' => 'Indemnité forfaitaire de 40 €', 'subject_code' => 'PMT']], $data['notes']);
        $this->assertSame('2LE', $data['seller']['name']);
        $this->assertSame('440138717', $data['seller']['siren']);
        $this->assertSame('', $data['seller']['address']['city']);
        $this->assertSame('FR', $data['seller']['address']['country_code']);
    }

    public function testWarmupCreatesTheEmptyLines(): void
    {
        $created = [];
        $repository = $this->createMock(AbstractConfigRepository::class);
        $init = function (string $group, string $key, string $default) use (&$created): string {
            $created[$group . '.' . $key] = $default;

            return $default;
        };
        $repository->method('initString')->willReturnCallback($init);
        $repository->method('initText')->willReturnCallback($init);

        (new FacturXConfigWarmup())->warmup($repository);

        $this->assertCount(13, $created);
        $this->assertSame([''], array_values(array_unique($created)));
        $this->assertArrayHasKey('facturx.note_pmt', $created);
    }
}
