<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep qteSt as FLOAT in lignepiece table for decimal stock quantities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lignepiece CHANGE qte_st qte_st FLOAT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lignepiece CHANGE qte_st qte_st FLOAT DEFAULT NULL');
    }
}
