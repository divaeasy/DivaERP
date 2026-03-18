<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add theme column to dossier table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dossier ADD theme VARCHAR(50) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dossier DROP theme");
    }
}
