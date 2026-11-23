<?php

namespace Lle\FacturXBundle\Tests\Command;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Lle\FacturXBundle\Command\RefreshStatusesCommand;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceInterface;
use Lle\FacturXBundle\Invoice\InvoicePdfProviderInterface;
use Lle\FacturXBundle\Invoice\InvoiceSender;
use Lle\FacturXBundle\Model\Status;
use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\PlatformInterface;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestCreditNote;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestCustomer;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestInvoice;
use Lle\FacturXBundle\Tests\Invoice\InvoiceSenderTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** The cron finds the invoice entities by itself and refreshes the invoices in progress. */
class RefreshStatusesCommandTest extends TestCase
{
    public function testFindsTheInvoiceEntitiesInTheMapping(): void
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([
            new ClassMetadata(TestInvoice::class),
            new ClassMetadata(TestCreditNote::class),
            new ClassMetadata(TestCustomer::class),
        ]);
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getMetadataFactory')->willReturn($factory);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagers')->willReturn(['default' => $manager]);
        $sender = new InvoiceSender($this->createMock(PlatformInterface::class), $this->createMock(InvoicePdfProviderInterface::class), $doctrine);

        // the child entity is found through its parent, the customer is not an invoice
        $this->assertSame([TestInvoice::class], (new RefreshStatusesCommand($sender, $doctrine))->getInvoiceClasses());
    }

    public function testRefreshesTheInvoicesInProgress(): void
    {
        $invoice = InvoiceSenderTest::invoice()->setPlatformId('id-1')->setPlatformStatus('SUBMITTED');
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('getStatusHistory')->with('id-1')->willReturn([
            new Status(new \DateTimeImmutable('2026-11-20T13:53:13Z'), StatusCode::RECEIVED, 'RECEIVED'),
        ]);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $sender = new InvoiceSender($platform, $this->createMock(InvoicePdfProviderInterface::class), $doctrine);
        $command = new class ($sender, $doctrine, $invoice) extends RefreshStatusesCommand {
            public function __construct(InvoiceSender $sender, ManagerRegistry $doctrine, private ElectronicInvoiceInterface $invoice)
            {
                parent::__construct($sender, $doctrine);
            }

            protected function findInProgress(\DateTimeInterface $since): array
            {
                return [$this->invoice];
            }
        };
        $tester = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame('RECEIVED', $invoice->getPlatformStatus());
        $this->assertStringContainsString('1 invoice(s) checked, 0 error(s)', $tester->getDisplay());
    }
}
