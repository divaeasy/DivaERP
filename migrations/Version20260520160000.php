<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Code operation data migration: ensure 4 defaults, backfill pieces/line sens, and add performance indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_devis, piece_type_commande, piece_type_b_l, piece_type_facture, piece_type_interne, created_at, updated_at) VALUES ('Vente Standard', 'Sortie', 1, 1, 1, 1, 1, 0, NOW(), NOW())");
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_devis, piece_type_commande, piece_type_b_l, piece_type_facture, piece_type_interne, created_at, updated_at) VALUES ('Achat Standard', 'Entree', 1, 1, 1, 1, 1, 0, NOW(), NOW())");
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_devis, piece_type_commande, piece_type_b_l, piece_type_facture, piece_type_interne, created_at, updated_at) VALUES ('Transfert Interne Sortie', 'Sortie', 1, 0, 0, 0, 0, 1, NOW(), NOW())");
        $this->addSql("INSERT IGNORE INTO code_operation (libelle, sens, is_active, piece_type_devis, piece_type_commande, piece_type_b_l, piece_type_facture, piece_type_interne, created_at, updated_at) VALUES ('Transfert Interne Entree', 'Entree', 1, 0, 0, 0, 0, 1, NOW(), NOW())");

        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Achat Standard' LIMIT 1) WHERE ep.code_operation_id IS NULL AND LOWER(COALESCE(ep.typet, '')) = 'fournisseur'");
        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Transfert Interne Sortie' LIMIT 1) WHERE ep.code_operation_id IS NULL AND LOWER(COALESCE(ep.typet, '')) IN ('interne', 'tiersinterne', 'tiers interne')");
        $this->addSql("UPDATE entetepiece ep SET code_operation_id = (SELECT co.id FROM code_operation co WHERE co.libelle = 'Vente Standard' LIMIT 1) WHERE ep.code_operation_id IS NULL");

        $this->addSql('UPDATE lignepiece lp INNER JOIN entetepiece ep ON ep.id = lp.piece_id INNER JOIN code_operation co ON co.id = ep.code_operation_id SET lp.sens = co.sens WHERE lp.sens IS NULL');

        $entete = $schema->getTable('entetepiece');
        if (!$entete->hasIndex('IDX_ENTETEPIECE_TYPET_CODE_OPERATION')) {
            $this->addSql('CREATE INDEX IDX_ENTETEPIECE_TYPET_CODE_OPERATION ON entetepiece (typet, code_operation_id)');
        }

        $ligne = $schema->getTable('lignepiece');
        if (!$ligne->hasIndex('IDX_LIGNEPIECE_SENS')) {
            $this->addSql('CREATE INDEX IDX_LIGNEPIECE_SENS ON lignepiece (sens)');
        }

        $codeOperation = $schema->getTable('code_operation');
        if (!$codeOperation->hasIndex('IDX_CODE_OPERATION_ACTIVE_SENS')) {
            $this->addSql('CREATE INDEX IDX_CODE_OPERATION_ACTIVE_SENS ON code_operation (is_active, sens)');
        }
    }

    public function down(Schema $schema): void
    {
        $entete = $schema->getTable('entetepiece');
        if ($entete->hasIndex('IDX_ENTETEPIECE_TYPET_CODE_OPERATION')) {
            $this->addSql('DROP INDEX IDX_ENTETEPIECE_TYPET_CODE_OPERATION ON entetepiece');
        }

        $ligne = $schema->getTable('lignepiece');
        if ($ligne->hasIndex('IDX_LIGNEPIECE_SENS')) {
            $this->addSql('DROP INDEX IDX_LIGNEPIECE_SENS ON lignepiece');
        }

        $codeOperation = $schema->getTable('code_operation');
        if ($codeOperation->hasIndex('IDX_CODE_OPERATION_ACTIVE_SENS')) {
            $this->addSql('DROP INDEX IDX_CODE_OPERATION_ACTIVE_SENS ON code_operation');
        }
    }
}
