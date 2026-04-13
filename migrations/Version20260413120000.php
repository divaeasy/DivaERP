<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fournisseur table based on clients structure with dossier and name indexes';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('fournisseur')) {
            return;
        }

        $this->addSql('CREATE TABLE fournisseur (
            id INT AUTO_INCREMENT NOT NULL,
            dossier_id INT NOT NULL,
            ville_id INT DEFAULT NULL,
            pays_id INT DEFAULT NULL,
            tarif_id INT DEFAULT NULL,
            reglement_id INT DEFAULT NULL,
            created_by_id INT DEFAULT NULL,
            modifed_by_id INT DEFAULT NULL,
            nom VARCHAR(255) NOT NULL,
            adr1 VARCHAR(255) NOT NULL,
            adr2 VARCHAR(255) DEFAULT NULL,
            rue VARCHAR(255) NOT NULL,
            codepostal INT DEFAULT NULL,
            tel VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            web VARCHAR(255) DEFAULT NULL,
            linkedin VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX IDX_FOURNISSEUR_DOSSIER (dossier_id),
            INDEX IDX_FOURNISSEUR_NOM (nom),
            INDEX IDX_FOURNISSEUR_VILLE (ville_id),
            INDEX IDX_FOURNISSEUR_PAYS (pays_id),
            INDEX IDX_FOURNISSEUR_TARIF (tarif_id),
            INDEX IDX_FOURNISSEUR_REGLEMENT (reglement_id),
            INDEX IDX_FOURNISSEUR_CREATED_BY (created_by_id),
            INDEX IDX_FOURNISSEUR_MODIFED_BY (modifed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_DOSSIER FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_VILLE FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_PAYS FOREIGN KEY (pays_id) REFERENCES pays (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_TARIF FOREIGN KEY (tarif_id) REFERENCES tarifs (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_REGLEMENT FOREIGN KEY (reglement_id) REFERENCES reglement (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_FOURNISSEUR_MODIFED_BY FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('fournisseur')) {
            return;
        }

        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_DOSSIER');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_VILLE');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_PAYS');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_TARIF');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_REGLEMENT');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_CREATED_BY');
        $this->addSql('ALTER TABLE fournisseur DROP FOREIGN KEY FK_FOURNISSEUR_MODIFED_BY');
        $this->addSql('DROP TABLE fournisseur');
    }
}
