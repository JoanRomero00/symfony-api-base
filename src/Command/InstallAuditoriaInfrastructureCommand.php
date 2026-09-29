<?php

namespace App\Command;

use App\Service\AuditoriaInfrastructureInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auditoria:instalar',
    description: 'Instala o actualiza el esquema y las funciones base de auditoría.',
)]
final class InstallAuditoriaInfrastructureCommand extends Command
{
    public function __construct(
        private AuditoriaInfrastructureInstaller $installer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->installer->install();
        } catch (\Throwable $exception) {
            $io->error(sprintf(
                'No se pudo instalar la infraestructura de auditoría: %s',
                $exception->getMessage()
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Infraestructura de auditoría disponible en el esquema "%s".',
            $this->installer->getSchemaName()
        ));

        return Command::SUCCESS;
    }
}
