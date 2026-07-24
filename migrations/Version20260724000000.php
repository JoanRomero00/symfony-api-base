<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\EntitySchema;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite nombre opcional en el CRUD de usuarios.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            'ALTER TABLE %s.usuario ALTER nombre DROP NOT NULL',
            EntitySchema::MAIN,
        ));
    }

    public function down(Schema $schema): void
    {
        $this->addSql(sprintf(
            "UPDATE %s.usuario SET nombre = '' WHERE nombre IS NULL",
            EntitySchema::MAIN,
        ));
        $this->addSql(sprintf(
            'ALTER TABLE %s.usuario ALTER nombre SET NOT NULL',
            EntitySchema::MAIN,
        ));
    }
}
