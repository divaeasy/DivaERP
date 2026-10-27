<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327084317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dossier address/legal/bank/numbering fields and relax nullable client contact fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients CHANGE adr2 adr2 VARCHAR(255) DEFAULT NULL, CHANGE tel tel VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE web web VARCHAR(255) DEFAULT NULL, CHANGE linkedin linkedin VARCHAR(255) DEFAULT NULL');

        $dossier = $schema->getTable('dossier');
        if (!$dossier->hasColumn('codepostal')) {
            $this->addSql('ALTER TABLE dossier ADD codepostal VARCHAR(20) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('ville')) {
            $this->addSql('ALTER TABLE dossier ADD ville VARCHAR(120) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('pays')) {
            $this->addSql('ALTER TABLE dossier ADD pays VARCHAR(120) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('siret')) {
            $this->addSql('ALTER TABLE dossier ADD siret VARCHAR(20) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('naf')) {
            $this->addSql('ALTER TABLE dossier ADD naf VARCHAR(20) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('tvaintra')) {
            $this->addSql('ALTER TABLE dossier ADD tvaintra VARCHAR(20) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('email')) {
            $this->addSql('ALTER TABLE dossier ADD email VARCHAR(40) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('tel')) {
            $this->addSql('ALTER TABLE dossier ADD tel VARCHAR(40) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('iban')) {
            $this->addSql('ALTER TABLE dossier ADD iban VARCHAR(50) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('bic')) {
            $this->addSql('ALTER TABLE dossier ADD bic VARCHAR(20) DEFAULT NULL');
        }
        if (!$dossier->hasColumn('devisno')) {
            $this->addSql('ALTER TABLE dossier ADD devisno INT DEFAULT NULL');
        }
        if (!$dossier->hasColumn('cmdno')) {
            $this->addSql('ALTER TABLE dossier ADD cmdno INT DEFAULT NULL');
        }
        if (!$dossier->hasColumn('blno')) {
            $this->addSql('ALTER TABLE dossier ADD blno INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients CHANGE adr2 adr2 VARCHAR(255) NOT NULL, CHANGE tel tel VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE web web VARCHAR(255) NOT NULL, CHANGE linkedin linkedin VARCHAR(255) NOT NULL');

        $dossier = $schema->getTable('dossier');
        if ($dossier->hasColumn('codepostal')) {
            $this->addSql('ALTER TABLE dossier DROP codepostal');
        }
        if ($dossier->hasColumn('ville')) {
            $this->addSql('ALTER TABLE dossier DROP ville');
        }
        if ($dossier->hasColumn('pays')) {
            $this->addSql('ALTER TABLE dossier DROP pays');
        }
        if ($dossier->hasColumn('siret')) {
            $this->addSql('ALTER TABLE dossier DROP siret');
        }
        if ($dossier->hasColumn('naf')) {
            $this->addSql('ALTER TABLE dossier DROP naf');
        }
        if ($dossier->hasColumn('tvaintra')) {
            $this->addSql('ALTER TABLE dossier DROP tvaintra');
        }
        if ($dossier->hasColumn('email')) {
            $this->addSql('ALTER TABLE dossier DROP email');
        }
        if ($dossier->hasColumn('tel')) {
            $this->addSql('ALTER TABLE dossier DROP tel');
        }
        if ($dossier->hasColumn('iban')) {
            $this->addSql('ALTER TABLE dossier DROP iban');
        }
        if ($dossier->hasColumn('bic')) {
            $this->addSql('ALTER TABLE dossier DROP bic');
        }
        if ($dossier->hasColumn('devisno')) {
            $this->addSql('ALTER TABLE dossier DROP devisno');
        }
        if ($dossier->hasColumn('cmdno')) {
            $this->addSql('ALTER TABLE dossier DROP cmdno');
        }
        if ($dossier->hasColumn('blno')) {
            $this->addSql('ALTER TABLE dossier DROP blno');
        }
    }
}

