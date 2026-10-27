<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase entetepiece.typet and entetepiece.statut length to support workflow values';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('entetepiece')) {
            return;
        }

        $table = $schema->getTable('entetepiece');
        if ($table->hasColumn('typet')) {
            $this->addSql('ALTER TABLE entetepiece CHANGE typet typet VARCHAR(20) NOT NULL');
        }
        if ($table->hasColumn('statut')) {
            $this->addSql('ALTER TABLE entetepiece CHANGE statut statut VARCHAR(20) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('entetepiece')) {
            return;
        }

        $table = $schema->getTable('entetepiece');
        if ($table->hasColumn('typet')) {
            $this->addSql('ALTER TABLE entetepiece CHANGE typet typet VARCHAR(8) NOT NULL');
        }
        if ($table->hasColumn('statut')) {
            $this->addSql('ALTER TABLE entetepiece CHANGE statut statut VARCHAR(8) DEFAULT NULL');
        }
    }
}

