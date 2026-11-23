<?php

namespace Lle\FacturXBundle\Pdf;

use Lle\PdfGeneratorBundle\Converter\PdfToPdfArchiveConverter;

/**
 * PDF/A-3 with the Factur-X XML attached, as the French platforms expect it: attachment "factur-x.xml",
 * XMP metadata Factur-X 1.0 / EN 16931. Available when 2lenet/pdf-generator-bundle is installed; otherwise make the
 * PDF/A-3 with your own tools.
 */
class FacturXPdfConverter extends PdfToPdfArchiveConverter
{
    public const ZUGFERD_XML_FILE_NAME = 'factur-x.xml';
    public const VERSION = '1.0';
    public const CONFORMANCE_LEVEL = 'EN 16931';

    public function convertToZugferdPdf(string $invoicePdf, string $zugferdXml, array $metadata): string
    {
        return parent::convertToZugferdPdf($invoicePdf, $zugferdXml, array_merge([
            'version' => self::VERSION,
            'conformanceLevel' => self::CONFORMANCE_LEVEL,
        ], $metadata));
    }
}
