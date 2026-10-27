<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock management columns (qte_st, mouvement_de_stock) and stock_actuel to article';
    }

    public function up(Schema $schema): void
    {
        // Add qteSt column to lignepiece
        $this->addSql('ALTER TABLE lignepiece ADD COLUMN qte_st FLOAT NULL COMMENT "Stock Quantity (QteSt)"');
        
        // Add mouvementDeStock column to lignepiece
        $this->addSql('ALTER TABLE lignepiece ADD COLUMN mouvement_de_stock VARCHAR(255) NULL COMMENT "Reference to consumed entry piece"');
        
        // Add stock_actuel column to article for performance caching
        $this->addSql('ALTER TABLE article ADD COLUMN stock_actuel FLOAT DEFAULT 0 COMMENT "Cached current stock value"');
        
        // Populate qteSt for existing BL and Facture pieces with Entree direction
        $this->addSql(<<<'SQL'
            UPDATE lignepiece lp
            INNER JOIN entetepiece ep ON lp.piece_id = ep.id
            INNER JOIN code_operation co ON ep.code_operation_id = co.id
            SET lp.qte_st = lp.qte
            WHERE (ep.type = 'BL' OR ep.type = 'Facture')
            AND co.sens = 'Entree'
            AND lp.qte_st IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lignepiece DROP COLUMN qte_st');
        $this->addSql('ALTER TABLE lignepiece DROP COLUMN mouvement_de_stock');
        $this->addSql('ALTER TABLE article DROP COLUMN stock_actuel');
    }
}
