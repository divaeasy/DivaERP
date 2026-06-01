<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add code_operation model, link it to entetepiece, and add lignepiece.sens with backfill';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('code_operation')) {
            $this->addSql('CREATE TABLE code_operation (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, modifed_by_id INT DEFAULT NULL, libelle VARCHAR(255) NOT NULL, sens VARCHAR(20) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, piece_type_devis TINYINT(1) NOT NULL DEFAULT 0, piece_type_commande TINYINT(1) NOT NULL DEFAULT 0, piece_type_b_l TINYINT(1) NOT NULL DEFAULT 0, piece_type_facture TINYINT(1) NOT NULL DEFAULT 0, piece_type_interne TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CODE_OPERATION_LIBELLE (libelle), INDEX IDX_CODE_OPERATION_CREATED_BY (created_by_id), INDEX IDX_CODE_OPERATION_MODIFED_BY (modifed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE code_operation ADD CONSTRAINT FK_CODE_OPERATION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE code_operation ADD CONSTRAINT FK_CODE_OPERATION_MODIFED_BY FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        }

        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_facture, piece_type_interne) VALUES ('Vente Standard', 'Sortie', 1, 1, 0)");
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_facture, piece_type_interne) VALUES ('Achat Standard', 'Entree', 1, 1, 0)");
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_facture, piece_type_interne) VALUES ('Operation Interne', 'Entree', 1, 0, 1)");

        if (!$schema->hasTable('entetepiece')) {
            return;
        }

        $enteteTable = $schema->getTable('entetepiece');
        if (!$enteteTable->hasColumn('code_operation_id')) {
            $this->addSql('ALTER TABLE entetepiece ADD code_operation_id INT DEFAULT NULL');
        }
        if (!$enteteTable->hasColumn('tier_destination_id')) {
            $this->addSql('ALTER TABLE entetepiece ADD tier_destination_id INT DEFAULT NULL');
        }
        if (!$enteteTable->hasIndex('IDX_ENTETEPIECE_CODE_OPERATION_ID')) {
            $this->addSql('CREATE INDEX IDX_ENTETEPIECE_CODE_OPERATION_ID ON entetepiece (code_operation_id)');
        }
        if (!$enteteTable->hasIndex('IDX_ENTETEPIECE_TIER_DESTINATION_ID')) {
            $this->addSql('CREATE INDEX IDX_ENTETEPIECE_TIER_DESTINATION_ID ON entetepiece (tier_destination_id)');
        }

        $hasCodeOperationFk = false;
        $hasTierDestinationFk = false;
        foreach ($enteteTable->getForeignKeys() as $foreignKey) {
            $localColumns = $foreignKey->getLocalColumns();
            if (in_array('code_operation_id', $localColumns, true)) {
                $hasCodeOperationFk = true;
            }
            if (in_array('tier_destination_id', $localColumns, true)) {
                $hasTierDestinationFk = true;
            }
        }
        if (!$hasCodeOperationFk) {
            $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_ENTETEPIECE_CODE_OPERATION FOREIGN KEY (code_operation_id) REFERENCES code_operation (id)');
        }
        if (!$hasTierDestinationFk && $schema->hasTable('tiers_interne')) {
            $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_ENTETEPIECE_TIER_DESTINATION FOREIGN KEY (tier_destination_id) REFERENCES tiers_interne (id)');
        }

        $this->addSql("UPDATE entetepiece SET typet = 'Interne' WHERE LOWER(COALESCE(typet, '')) IN ('tiers interne', 'tiersinterne')");

        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Achat Standard' LIMIT 1) WHERE ep.code_operation_id IS NULL AND LOWER(COALESCE(ep.typet, '')) = 'fournisseur'");
        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Operation Interne' LIMIT 1) WHERE ep.code_operation_id IS NULL AND (LOWER(COALESCE(ep.typet, '')) = 'interne' OR LOWER(COALESCE(ep.typet, '')) = 'tiersinterne' OR LOWER(COALESCE(ep.typet, '')) = 'tiers interne')");
        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Vente Standard' LIMIT 1) WHERE ep.code_operation_id IS NULL");
        $this->addSql('ALTER TABLE entetepiece CHANGE code_operation_id code_operation_id INT NOT NULL');

        if (!$schema->hasTable('lignepiece')) {
            return;
        }

        $ligneTable = $schema->getTable('lignepiece');
        if (!$ligneTable->hasColumn('sens')) {
            $this->addSql('ALTER TABLE lignepiece ADD sens VARCHAR(20) DEFAULT NULL');
        }

        $this->addSql('UPDATE lignepiece lp INNER JOIN entetepiece ep ON ep.id = lp.piece_id INNER JOIN code_operation co ON co.id = ep.code_operation_id SET lp.sens = co.sens WHERE lp.sens IS NULL');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('lignepiece')) {
            $ligneTable = $schema->getTable('lignepiece');
            if ($ligneTable->hasColumn('sens')) {
                $this->addSql('ALTER TABLE lignepiece DROP sens');
            }
        }

        if ($schema->hasTable('entetepiece')) {
            $enteteTable = $schema->getTable('entetepiece');

            foreach ($enteteTable->getForeignKeys() as $foreignKey) {
                $localColumns = $foreignKey->getLocalColumns();
                if (in_array('code_operation_id', $localColumns, true) || in_array('tier_destination_id', $localColumns, true)) {
                    $this->addSql(sprintf('ALTER TABLE entetepiece DROP FOREIGN KEY %s', $foreignKey->getName()));
                }
            }

            if ($enteteTable->hasIndex('IDX_ENTETEPIECE_CODE_OPERATION_ID')) {
                $this->addSql('DROP INDEX IDX_ENTETEPIECE_CODE_OPERATION_ID ON entetepiece');
            }
            if ($enteteTable->hasIndex('IDX_ENTETEPIECE_TIER_DESTINATION_ID')) {
                $this->addSql('DROP INDEX IDX_ENTETEPIECE_TIER_DESTINATION_ID ON entetepiece');
            }
            if ($enteteTable->hasColumn('code_operation_id')) {
                $this->addSql('ALTER TABLE entetepiece DROP code_operation_id');
            }
            if ($enteteTable->hasColumn('tier_destination_id')) {
                $this->addSql('ALTER TABLE entetepiece DROP tier_destination_id');
            }
        }

        if ($schema->hasTable('code_operation')) {
            $this->addSql('DROP TABLE code_operation');
        }
    }
}
