<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditoriaInfrastructureTest extends KernelTestCase
{
    public function testInstallerIsIdempotentAndLeavesInfrastructureComplete(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:auditoria:instalar');
        $commandTester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString(
            'Infraestructura de auditoría disponible',
            $commandTester->getDisplay()
        );

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString(
            'Infraestructura de auditoría disponible',
            $commandTester->getDisplay()
        );
    }
}
