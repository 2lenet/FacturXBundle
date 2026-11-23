<?php

namespace Lle\FacturXBundle\Tests\Invoice;

use Doctrine\Persistence\ManagerRegistry;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceInterface;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceTrait;
use Lle\FacturXBundle\Invoice\InvoicePdfProviderInterface;
use Lle\FacturXBundle\Invoice\InvoiceSender;
use Lle\FacturXBundle\Model\Status;
use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\PlatformInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Sending kept on the invoice, sending again only after a rejection, most recent status. */
class InvoiceSenderTest extends TestCase
{
    private PlatformInterface&MockObject $platform;

    private InvoiceSender $sender;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(PlatformInterface::class);
        $pdfProvider = $this->createMock(InvoicePdfProviderInterface::class);
        $pdfProvider->method('getPdf')->willReturnCallback(fn (): string => (string)tempnam(sys_get_temp_dir(), 'invoice'));
        $this->sender = new InvoiceSender($this->platform, $pdfProvider, $this->createMock(ManagerRegistry::class));
    }

    public function testIsSent(): void
    {
        $invoice = self::invoice();
        $this->assertFalse($this->sender->isSent($invoice));
        $this->assertTrue($this->sender->isSent($invoice->setPlatformId('id-1')->setPlatformStatus('RECEIVED')));
        // a rejected invoice can be sent again
        $this->assertFalse($this->sender->isSent($invoice->setPlatformStatus('REJECTED')));
    }

    public function testSendKeepsTheFollowUpOnTheInvoice(): void
    {
        $this->platform->expects($this->once())->method('send')->willReturn('platform-id-1');
        $invoice = self::invoice()->setPlatformId('old')->setPlatformStatus('REJECTED')->setPlatformReasons('BR-FR-08');

        $this->sender->send($invoice);

        $this->assertSame('platform-id-1', $invoice->getPlatformId());
        $this->assertSame('SUBMITTED', $invoice->getPlatformStatus());
        $this->assertNull($invoice->getPlatformReasons());
        $this->assertNotNull($invoice->getPlatformSentAt());
    }

    public function testRefreshStatusKeepsTheMostRecent(): void
    {
        $this->platform->method('getStatusHistory')->with('id-1')->willReturn([
            new Status(new \DateTimeImmutable('2026-11-20T13:54:00Z'), StatusCode::REJECTED, 'REJECTED', 'OPERATOR', ['BR-FR-16 : rate', 'BR-FR-05 : notes']),
            new Status(new \DateTimeImmutable('2026-11-20T13:53:13Z'), StatusCode::RECEIVED, 'RECEIVED'),
        ]);
        $invoice = self::invoice()->setPlatformId('id-1')->setPlatformStatus('SUBMITTED');

        $this->sender->refreshStatus($invoice);

        $this->assertSame('REJECTED', $invoice->getPlatformStatus());
        $this->assertSame("BR-FR-16 : rate\nBR-FR-05 : notes", $invoice->getPlatformReasons());
    }

    public static function invoice(): ElectronicInvoiceInterface
    {
        return new class implements ElectronicInvoiceInterface {
            use ElectronicInvoiceTrait;
        };
    }
}
