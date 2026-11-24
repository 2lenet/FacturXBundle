<?php

namespace Lle\FacturXBundle\Tests\Pdf;

use Lle\FacturXBundle\DependencyInjection\LleFacturXExtension;
use Lle\FacturXBundle\Pdf\FacturXPdfConverter;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** PDF/A-3 as the French platforms expect it: attachment factur-x.xml, XMP metadata Factur-X 1.0 / EN 16931. */
class FacturXPdfConverterTest extends TestCase
{
    public function testFacturXAttachmentAndMetadata(): void
    {
        $loader = new FilesystemLoader();
        $views = dirname((string)(new \ReflectionClass(FacturXPdfConverter::class))->getParentClass()->getFileName(), 2) . '/Resources/views';
        $loader->addPath($views, 'LlePdfGenerator');
        $source = tempnam(sys_get_temp_dir(), 'pdf') . '.pdf';
        $fpdi = new Fpdi();
        $fpdi->AddPage();
        $fpdi->Output('F', $source);

        $result = (new FacturXPdfConverter(new Environment($loader)))->convertToZugferdPdf($source, '<rsm:CrossIndustryInvoice/>', ['title' => 'F-1']);
        $pdf = (string)file_get_contents($result);
        unlink($source);
        unlink($result);

        $this->assertStringContainsString('(factur-x.xml)', $pdf);
        $this->assertStringNotContainsString('zugferd-invoice.xml', $pdf);
        $this->assertStringContainsString('<fx:Version>1.0</fx:Version>', $pdf);
        $this->assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $pdf);
    }

    public function testDeclaredWhenPdfGeneratorBundleIsInstalled(): void
    {
        $container = new ContainerBuilder();
        (new LleFacturXExtension())->load([[]], $container);

        $this->assertTrue($container->hasDefinition(FacturXPdfConverter::class));
    }
}
