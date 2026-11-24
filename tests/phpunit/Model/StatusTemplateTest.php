<?php

namespace Lle\FacturXBundle\Tests\Model;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** The status field shows the translated label, the code when unknown, nothing when empty. */
class StatusTemplateTest extends TestCase
{
    public function testLabelOfTheStatus(): void
    {
        $this->assertSame('Rejetée', $this->render('REJECTED'));
        $this->assertSame('NEW_CODE', $this->render('NEW_CODE'));
        $this->assertSame('', $this->render(null));
    }

    private function render(?string $value): string
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../src/Resources/translations/LleFacturXBundle.fr.yaml', 'fr', 'LleFacturXBundle');
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../src/Resources/views', 'LleFacturX');
        $twig = new Environment($loader);
        $twig->addExtension(new TranslationExtension($translator));

        return trim($twig->render('@LleFacturX/field/status.html.twig', ['value' => $value]));
    }
}
