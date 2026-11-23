<?php

namespace Lle\FacturXBundle\Tests\Command;

use Lle\FacturXBundle\Command\SendCommand;
use Lle\FacturXBundle\Model\Status;
use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\PlatformInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SendCommandTest extends TestCase
{
    public function testSendsTheFileAndShowsItsStatuses(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('send')->willReturn('platform-id-1');
        $platform->method('getStatusHistory')->with('platform-id-1')->willReturn([
            new Status(new \DateTimeImmutable('2026-11-20T13:53:13Z'), StatusCode::RECEIVED, 'RECEIVED', 'OPERATOR'),
        ]);
        $tester = new CommandTester(new SendCommand($platform));
        $file = (string)tempnam(sys_get_temp_dir(), 'invoice');

        $tester->execute(['file' => $file, '--wait' => 0]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('platform-id-1', $tester->getDisplay());
        $this->assertStringContainsString('RECEIVED', $tester->getDisplay());
        unlink($file);
    }

    public function testFailsWhenTheFileDoesNotExist(): void
    {
        $tester = new CommandTester(new SendCommand($this->createMock(PlatformInterface::class)));

        $this->assertSame(Command::FAILURE, $tester->execute(['file' => '/no/such/file.pdf', '--wait' => 0]));
    }
}
