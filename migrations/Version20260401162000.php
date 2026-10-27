<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional dossier late-payment legal text for classic invoice model';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('dossier')) {
            return;
        }

        $dossier = $schema->getTable('dossier');
        if (!$dossier->hasColumn('penalitesretard')) {
            $this->addSql('ALTER TABLE dossier ADD penalitesretard LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('dossier')) {
            return;
        }

        $dossier = $schema->getTable('dossier');
        if ($dossier->hasColumn('penalitesretard')) {
            $this->addSql('ALTER TABLE dossier DROP penalitesretard');
        }
    }
}
