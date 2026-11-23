<?php

namespace Lle\FacturXBundle\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** The EN 16931 Factur-X template: conformant elements, optional elements left out, escaped texts. */
class En16931TemplateTest extends TestCase
{
    public const TEMPLATE = '@LleFacturX/en16931.xml.twig';

    public function testCompleteInvoice(): void
    {
        $xml = $this->render(self::invoice());

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Fixtures/en16931-complete.xml', $xml);
    }

    public function testOptionalElementsAreLeftOut(): void
    {
        $invoice = self::invoice();
        unset($invoice['business_process'], $invoice['notes'], $invoice['due_date'], $invoice['buyer_reference']);
        unset($invoice['buyer']['siren'], $invoice['buyer']['electronic_address'], $invoice['buyer']['vat_number']);
        $xml = $this->render($invoice);

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringNotContainsString('BusinessProcessSpecifiedDocumentContextParameter', $xml);
        $this->assertStringNotContainsString('IncludedNote', $xml);
        $this->assertStringNotContainsString('SpecifiedTradePaymentTerms', $xml);
        $this->assertSame(1, substr_count($xml, '<ram:SpecifiedLegalOrganization>'));
        $this->assertSame(1, substr_count($xml, '<ram:URIUniversalCommunication>'));
        $this->assertSame(1, substr_count($xml, '<ram:SpecifiedTaxRegistration>'));
    }

    public function testTextsAreEscaped(): void
    {
        $invoice = self::invoice();
        $invoice['buyer']['name'] = 'Dupont & Fils <SARL>';
        $xml = $this->render($invoice);

        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document);
        $this->assertStringContainsString('Dupont &amp; Fils &lt;SARL&gt;', $xml);
    }

    /** @return array<string, mixed> */
    public static function invoice(): array
    {
        return [
            'number' => 'F2026-0042',
            'type_code' => '380',
            'issue_date' => new \DateTimeImmutable('2026-11-20'),
            'due_date' => new \DateTimeImmutable('2026-12-20'),
            'business_process' => 'S1',
            'buyer_reference' => 'BC-12',
            'notes' => [
                ['content' => 'Indemnité forfaitaire pour frais de recouvrement : 40 €', 'subject_code' => 'PMT'],
                ['content' => 'Pénalités de retard : 3 fois le taux d\'intérêt légal', 'subject_code' => 'PMD'],
                ['content' => 'Pas d\'escompte pour paiement anticipé', 'subject_code' => 'AAB'],
            ],
            'seller' => [
                'name' => '2LE',
                'siren' => '440138717',
                'vat_number' => 'FR74440138717',
                'electronic_address' => '440138717',
                'address' => ['line1' => '3 rue du Sauvage', 'postcode' => '68100', 'city' => 'Mulhouse'],
            ],
            'buyer' => [
                'name' => 'Example Buyer',
                'siren' => '732913413',
                'vat_number' => 'FR12732913413',
                'electronic_address' => '732913413',
                'address' => ['line1' => '1 place de la Gare', 'line2' => 'Bâtiment B', 'postcode' => '75001', 'city' => 'Paris', 'country_code' => 'FR'],
            ],
            'lines' => [
                ['label' => 'Sauvegarde 1 To', 'quantity' => 2, 'unit_price_excl_tax' => 49.9, 'vat_category' => 'S', 'vat_rate' => 20, 'total_excl_tax' => 99.8],
                ['label' => 'Livre', 'quantity' => 1, 'unit_price_excl_tax' => 10, 'vat_category' => 'S', 'vat_rate' => 5.5, 'total_excl_tax' => 10],
            ],
            'taxes' => [
                ['vat_category' => 'S', 'vat_rate' => 20, 'basis_amount' => 99.8, 'tax_amount' => 19.96],
                ['vat_category' => 'S', 'vat_rate' => 5.5, 'basis_amount' => 10, 'tax_amount' => 0.55],
            ],
            'totals' => ['total_excl_tax' => 109.8, 'total_tax' => 20.51, 'total_incl_tax' => 130.31],
        ];
    }

    /** @param array<string, mixed> $invoice */
    private function render(array $invoice): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../src/Resources/views', 'LleFacturX');

        return (new Environment($loader, ['strict_variables' => true]))->render(self::TEMPLATE, ['invoice' => $invoice]);
    }
}
