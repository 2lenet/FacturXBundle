<?php

namespace Lle\FacturXBundle\Command;

use Lle\FacturXBundle\Model\Status;
use Lle\FacturXBundle\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'lle:facturx:send',
    description: 'Sends an invoice file (Factur-X PDF or XML) to the approved platform and shows its statuses',
)]
class SendCommand extends Command
{
    public const ARGUMENT_FILE = 'file';
    public const OPTION_WAIT = 'wait';
    public const DEFAULT_WAIT = 5;

    public function __construct(protected PlatformInterface $platform)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'Path of the invoice file')
            ->addOption(
                self::OPTION_WAIT,
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds to wait before reading the statuses (asynchronous processing on the platform)',
                self::DEFAULT_WAIT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string)$input->getArgument(self::ARGUMENT_FILE);
        if (!is_file($file)) {
            $io->error(sprintf('File not found: %s', $file));

            return Command::FAILURE;
        }

        $id = $this->platform->send($file);
        $io->success(sprintf('Invoice sent, id on the platform: %s', $id));

        sleep((int)$input->getOption(self::OPTION_WAIT));

        $rows = array_map(fn (Status $status): array => [
            $status->date->format(\DateTimeInterface::ATOM),
            $status->getLabel(),
            $status->recipient,
            implode("\n", $status->reasons),
        ], $this->platform->getStatusHistory($id));
        $io->table(['Date', 'Status', 'Recipient', 'Reasons'], $rows);

        return Command::SUCCESS;
    }
}
