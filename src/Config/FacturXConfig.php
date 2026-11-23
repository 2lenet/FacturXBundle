<?php

namespace Lle\FacturXBundle\Config;

use Doctrine\ORM\EntityManagerInterface;
use Lle\ConfigBundle\Contracts\ConfigInterface;
use Lle\ConfigBundle\Repository\AbstractConfigRepository;

/**
 * Seller, billing framework and notes of the Factur-X invoices, read from the config screen of 2lenet/config-bundle
 * (group "facturx"). Enabled by lle_facturx.config_screen.
 */
class FacturXConfig
{
    public const GROUP = 'facturx';

    public const SELLER_NAME = 'seller_name';
    public const SELLER_ADDRESS1 = 'seller_address1';
    public const SELLER_ADDRESS2 = 'seller_address2';
    public const SELLER_POSTCODE = 'seller_postcode';
    public const SELLER_CITY = 'seller_city';
    public const SELLER_COUNTRY = 'seller_country';
    public const SELLER_SIREN = 'seller_siren';
    public const SELLER_VAT_NUMBER = 'seller_vat_number';
    public const SELLER_ELECTRONIC_ADDRESS = 'seller_electronic_address';
    public const BUSINESS_PROCESS = 'business_process';
    public const NOTE_PMT = 'note_pmt';
    public const NOTE_PMD = 'note_pmd';
    public const NOTE_AAB = 'note_aab';

    /** One-line values (string) */
    public const KEYS = [
        self::SELLER_NAME,
        self::SELLER_ADDRESS1,
        self::SELLER_ADDRESS2,
        self::SELLER_POSTCODE,
        self::SELLER_CITY,
        self::SELLER_COUNTRY,
        self::SELLER_SIREN,
        self::SELLER_VAT_NUMBER,
        self::SELLER_ELECTRONIC_ADDRESS,
        self::BUSINESS_PROCESS,
    ];

    /** Notes (text) and their subject code: recovery fee, late penalties, discount */
    public const NOTES = [
        self::NOTE_PMT => 'PMT',
        self::NOTE_PMD => 'PMD',
        self::NOTE_AAB => 'AAB',
    ];

    public function __construct(protected EntityManagerInterface $em)
    {
    }

    /**
     * Part of the `invoice` array of the template @LleFacturX/en16931.xml.twig
     * (see README.md): business_process, notes and seller.
     *
     * @return array{business_process: string, notes: list<array{content: string, subject_code: string}>, seller: array<string, mixed>}
     */
    public function getInvoiceData(): array
    {
        $notes = [];
        foreach (self::NOTES as $key => $subjectCode) {
            $content = trim($this->getRepository()->getText(self::GROUP, $key, ''));
            if ($content !== '') {
                $notes[] = ['content' => $content, 'subject_code' => $subjectCode];
            }
        }

        return [
            'business_process' => $this->get(self::BUSINESS_PROCESS),
            'notes' => $notes,
            'seller' => [
                'name' => $this->get(self::SELLER_NAME),
                'siren' => $this->get(self::SELLER_SIREN),
                'vat_number' => $this->get(self::SELLER_VAT_NUMBER),
                'electronic_address' => $this->get(self::SELLER_ELECTRONIC_ADDRESS),
                'address' => [
                    'line1' => $this->get(self::SELLER_ADDRESS1),
                    'line2' => $this->get(self::SELLER_ADDRESS2),
                    'postcode' => $this->get(self::SELLER_POSTCODE),
                    'city' => $this->get(self::SELLER_CITY),
                    'country_code' => $this->get(self::SELLER_COUNTRY) ?: 'FR',
                ],
            ],
        ];
    }

    public function get(string $key): string
    {
        return trim($this->getRepository()->getString(self::GROUP, $key, ''));
    }

    protected function getRepository(): AbstractConfigRepository
    {
        /** @var AbstractConfigRepository $repository */
        $repository = $this->em->getRepository(ConfigInterface::class);

        return $repository;
    }
}
