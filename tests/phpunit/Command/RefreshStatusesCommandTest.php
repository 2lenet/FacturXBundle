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
use Lle\FacturXBundle\Tests\Command\Fixtures\TestAbstractInvoice;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestAbstractInvoiceChild;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestCreditNote;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestCustomer;
use Lle\FacturXBundle\Tests\Command\Fixtures\TestInvoice;
use Lle\FacturXBundle\Tests\Invoice\InvoiceSenderTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** The cron finds the invoice entities by itself and refreshes the invoices in progress. */
class RefreshStatusesCommandTest extends TestCase
{
    public function testFindsTheInvoiceEntitiesInTheMapping(): void
    {
        // the child entity is found through its parent, the customer is not an invoice
        $this->assertSame([TestInvoice::class], $this->invoiceClasses([
            $this->metadata(TestInvoice::class),
            $this->metadata(TestCreditNote::class, [TestInvoice::class]),
            $this->metadata(TestCustomer::class),
        ]));

        // abstract root of an inheritance (STI/JOINED): queried, it returns its children
        $this->assertSame([TestAbstractInvoice::class], $this->invoiceClasses([
            $this->metadata(TestAbstractInvoice::class),
            $this->metadata(TestAbstractInvoiceChild::class, [TestAbstractInvoice::class]),
        ]));

        // trait on a mapped superclass (no table): its child entity is queried
        $this->assertSame([TestCreditNote::class], $this->invoiceClasses([
            $this->metadata(TestInvoice::class, [], true),
            $this->metadata(TestCreditNote::class, [TestInvoice::class]),
        ]));
    }

    public function testRefreshesTheInvoicesInProgress(): void
    {
        $invoice = InvoiceSenderTest::invoice()->setPlatformId('id-1')->setPlatformStatus('SUBMITTED');
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('getStatusHistory')->with('id-1')->willReturn([
            new Status(new \DateTimeImmutable('2026-11-20T13:53:13Z'), StatusCode::RECEIVED, 'RECEIVED'),
        ]);
        $tester = new CommandTester($this->command($platform, [$invoice]));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame('RECEIVED', $invoice->getPlatformStatus());
        $this->assertStringContainsString('1 invoice(s) checked, 0 error(s)', $tester->getDisplay());
    }

    public function testAnInvoiceInErrorDoesNotStopTheOthers(): void
    {
        $broken = InvoiceSenderTest::invoice()->setPlatformId('id-1')->setPlatformStatus('SUBMITTED');
        $invoice = InvoiceSenderTest::invoice()->setPlatformId('id-2')->setPlatformStatus('SUBMITTED');
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('getStatusHistory')->willReturnCallback(fn (string $id): array => $id === 'id-1'
            ? throw new \RuntimeException('unexpected answer')
            : [new Status(new \DateTimeImmutable('2026-11-20T13:53:13Z'), StatusCode::RECEIVED, 'RECEIVED')]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $tester = new CommandTester($this->command($platform, [$broken, $invoice], $logger));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertSame('RECEIVED', $invoice->getPlatformStatus());
        $this->assertStringContainsString('unexpected answer', $tester->getDisplay());
        $this->assertStringContainsString('2 invoice(s) checked, 1 error(s)', $tester->getDisplay());
    }

    /** @param ElectronicInvoiceInterface[] $invoices */
    private function command(PlatformInterface $platform, array $invoices, LoggerInterface $logger = new NullLogger()): RefreshStatusesCommand
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $sender = new InvoiceSender($platform, $this->createMock(InvoicePdfProviderInterface::class), $doctrine);

        return new class ($sender, $doctrine, $logger, $invoices) extends RefreshStatusesCommand {
            /** @param ElectronicInvoiceInterface[] $invoices */
            public function __construct(InvoiceSender $sender, ManagerRegistry $doctrine, LoggerInterface $logger, private array $invoices)
            {
                parent::__construct($sender, $doctrine, $logger);
            }

            protected function findInProgress(\DateTimeInterface $since): array
            {
                return $this->invoices;
            }
        };
    }

    /**
     * @param ClassMetadata<object>[] $metadata
     *
     * @return string[]
     */
    private function invoiceClasses(array $metadata): array
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($metadata);
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getMetadataFactory')->willReturn($factory);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagers')->willReturn(['default' => $manager]);
        $sender = new InvoiceSender($this->createMock(PlatformInterface::class), $this->createMock(InvoicePdfProviderInterface::class), $doctrine);

        return (new RefreshStatusesCommand($sender, $doctrine, new NullLogger()))->getInvoiceClasses();
    }

    /**
     * @param class-string   $class
     * @param class-string[] $parents
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class, array $parents = [], bool $mappedSuperclass = false): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->parentClasses = $parents;
        $metadata->isMappedSuperclass = $mappedSuperclass;

        return $metadata;
    }
}
