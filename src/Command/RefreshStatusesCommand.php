<?php

namespace Lle\FacturXBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceInterface;
use Lle\FacturXBundle\Invoice\InvoiceSender;
use Lle\FacturXBundle\Model\StatusCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/** For a cron: the platform changes the status of the invoices after their sending. */
#[AsCommand(
    name: 'lle:facturx:refresh-statuses',
    description: 'Refreshes the status of the invoices sent to the approved platform whose processing is not over',
)]
class RefreshStatusesCommand extends Command
{
    public const OPTION_DAYS = 'days';
    public const DEFAULT_DAYS = 60;

    public function __construct(
        protected InvoiceSender $sender,
        protected ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(self::OPTION_DAYS, null, InputOption::VALUE_REQUIRED, 'Invoices sent for this number of days', self::DEFAULT_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $errors = 0;
        $invoices = $this->findInProgress(new \DateTime(sprintf('-%d days', (int)$input->getOption(self::OPTION_DAYS))));
        foreach ($invoices as $invoice) {
            $before = $invoice->getPlatformStatus();
            try {
                $this->sender->refreshStatus($invoice);
                $rows[] = [$this->name($invoice), $before, $invoice->getPlatformStatus(), (string)$invoice->getPlatformReasons()];
            } catch (HttpClientException $e) {
                ++$errors;
                $rows[] = [$this->name($invoice), $before, 'ERROR', $e->getMessage()];
            }
        }

        $io->table(['Invoice', 'Before', 'After', 'Reasons'], $rows);
        $io->success(sprintf('%d invoice(s) checked, %d error(s)', count($invoices), $errors));

        return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return ElectronicInvoiceInterface[] */
    protected function findInProgress(\DateTimeInterface $since): array
    {
        $invoices = [];
        foreach ($this->getInvoiceClasses() as $class) {
            $manager = $this->doctrine->getManagerForClass($class);
            if (!$manager instanceof EntityManagerInterface) {
                continue;
            }
            $found = $manager->createQueryBuilder()
                ->select('i')
                ->from($class, 'i')
                ->andWhere('i.platformId IS NOT NULL')
                ->andWhere('i.platformSentAt >= :since')
                ->andWhere('i.platformStatus IS NULL OR i.platformStatus NOT IN (:final)')
                ->setParameter('since', $since)
                ->setParameter('final', StatusCode::finalValues())
                ->orderBy('i.platformSentAt', 'ASC')
                ->getQuery()
                ->getResult();
            array_push($invoices, ...$found);
        }

        return $invoices;
    }

    /**
     * Entities of the project implementing ElectronicInvoiceInterface (ElectronicInvoiceTrait), found in the Doctrine
     * mapping: no configuration needed. A child entity is left out when its parent is already queried.
     *
     * @return class-string<ElectronicInvoiceInterface>[]
     */
    public function getInvoiceClasses(): array
    {
        $classes = [];
        foreach ($this->doctrine->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $class = $metadata->getName();
                if (!is_subclass_of($class, ElectronicInvoiceInterface::class)) {
                    continue;
                }
                $parents = array_filter(
                    class_parents($class) ?: [],
                    fn (string $parent): bool => is_subclass_of($parent, ElectronicInvoiceInterface::class)
                );
                if ($parents === [] && !(new \ReflectionClass($class))->isAbstract()) {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    protected function name(ElectronicInvoiceInterface $invoice): string
    {
        return $invoice instanceof \Stringable ? (string)$invoice : (string)$invoice->getPlatformId();
    }
}
