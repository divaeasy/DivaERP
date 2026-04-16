<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415161403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock management structures: nature_production, tiers_interne, depot, and stock fields on article and dossier';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('nature_production')) {
            $this->addSql('CREATE TABLE nature_production (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, modifed_by_id INT DEFAULT NULL, libelle VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3F930277A4D60759 (libelle), INDEX IDX_3F930277B03A8386 (created_by_id), INDEX IDX_3F930277880496E7 (modifed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE nature_production ADD CONSTRAINT FK_3F930277B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE nature_production ADD CONSTRAINT FK_3F930277880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        }

        if (!$schema->hasTable('tiers_interne')) {
            $this->addSql('CREATE TABLE tiers_interne (id INT AUTO_INCREMENT NOT NULL, dossier_id INT NOT NULL, ville_id INT DEFAULT NULL, pays_id INT DEFAULT NULL, tarif_id INT DEFAULT NULL, reglement_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, modifed_by_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, adr1 VARCHAR(255) NOT NULL, adr2 VARCHAR(255) DEFAULT NULL, rue VARCHAR(255) NOT NULL, codepostal INT DEFAULT NULL, tel VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, web VARCHAR(255) DEFAULT NULL, linkedin VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_EB7C3E4E611C0C56 (dossier_id), INDEX IDX_EB7C3E4EA73F0036 (ville_id), INDEX IDX_EB7C3E4EA6E44244 (pays_id), INDEX IDX_EB7C3E4E357C0A59 (tarif_id), INDEX IDX_EB7C3E4E6A477111 (reglement_id), INDEX IDX_EB7C3E4EB03A8386 (created_by_id), INDEX IDX_EB7C3E4E880496E7 (modifed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4E611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4EA73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4EA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4E357C0A59 FOREIGN KEY (tarif_id) REFERENCES tarifs (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4E6A477111 FOREIGN KEY (reglement_id) REFERENCES reglement (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4EB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE tiers_interne ADD CONSTRAINT FK_EB7C3E4E880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        }

        if (!$schema->hasTable('depot')) {
            $this->addSql('CREATE TABLE depot (id INT AUTO_INCREMENT NOT NULL, dossier_id INT NOT NULL, tiers_interne_id INT DEFAULT NULL, ville_id INT DEFAULT NULL, pays_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, modifed_by_id INT DEFAULT NULL, libelle VARCHAR(255) NOT NULL, adr1 VARCHAR(255) DEFAULT NULL, adr2 VARCHAR(255) DEFAULT NULL, rue VARCHAR(255) DEFAULT NULL, codepostal VARCHAR(20) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_47948BBC611C0C56 (dossier_id), INDEX IDX_47948BBC433437B (tiers_interne_id), INDEX IDX_47948BBCA73F0036 (ville_id), INDEX IDX_47948BBCA6E44244 (pays_id), INDEX IDX_47948BBCB03A8386 (created_by_id), INDEX IDX_47948BBC880496E7 (modifed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBC611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBC433437B FOREIGN KEY (tiers_interne_id) REFERENCES tiers_interne (id)');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBCA73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBCA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBCB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBC880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        }

        if ($schema->hasTable('article')) {
            $table = $schema->getTable('article');

            if (!$table->hasColumn('nature_production_id')) {
                $this->addSql('ALTER TABLE article ADD nature_production_id INT DEFAULT NULL');
            }
            if (!$table->hasColumn('fournisseur_habituel_id')) {
                $this->addSql('ALTER TABLE article ADD fournisseur_habituel_id INT DEFAULT NULL');
            }
            if (!$table->hasColumn('mode_gestion')) {
                $this->addSql("ALTER TABLE article ADD mode_gestion VARCHAR(20) DEFAULT 'En stock' NOT NULL");
            }
            if (!$table->hasColumn('mode_suivi')) {
                $this->addSql("ALTER TABLE article ADD mode_suivi VARCHAR(30) DEFAULT 'En quantité' NOT NULL");
            }
            if (!$table->hasColumn('sorti_stock')) {
                $this->addSql("ALTER TABLE article ADD sorti_stock VARCHAR(20) DEFAULT 'FIFO' NOT NULL");
            }
            if (!$table->hasIndex('IDX_23A0E666CCF1BA6')) {
                $this->addSql('CREATE INDEX IDX_23A0E666CCF1BA6 ON article (nature_production_id)');
            }
            if (!$table->hasIndex('IDX_23A0E669F8E1010')) {
                $this->addSql('CREATE INDEX IDX_23A0E669F8E1010 ON article (fournisseur_habituel_id)');
            }
            if (!$this->hasForeignKeyForColumn($table, 'nature_production_id')) {
                $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E666CCF1BA6 FOREIGN KEY (nature_production_id) REFERENCES nature_production (id)');
            }
            if (!$this->hasForeignKeyForColumn($table, 'fournisseur_habituel_id')) {
                $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E669F8E1010 FOREIGN KEY (fournisseur_habituel_id) REFERENCES fournisseur (id)');
            }
        }

        if ($schema->hasTable('dossier')) {
            $table = $schema->getTable('dossier');

            if (!$table->hasColumn('nature_stock_id')) {
                $this->addSql('ALTER TABLE dossier ADD nature_stock_id INT DEFAULT NULL');
            }
            if (!$table->hasColumn('sorti_stock_defaut')) {
                $this->addSql("ALTER TABLE dossier ADD sorti_stock_defaut VARCHAR(20) DEFAULT 'FIFO' NOT NULL");
            }
            if (!$table->hasColumn('gerer_stocks')) {
                $this->addSql('ALTER TABLE dossier ADD gerer_stocks TINYINT(1) DEFAULT 0 NOT NULL');
            }
            if (!$table->hasColumn('autoriser_stock_negatif')) {
                $this->addSql('ALTER TABLE dossier ADD autoriser_stock_negatif TINYINT(1) DEFAULT 0 NOT NULL');
            }
            if (!$table->hasIndex('IDX_3D48E037FB6CC400')) {
                $this->addSql('CREATE INDEX IDX_3D48E037FB6CC400 ON dossier (nature_stock_id)');
            }
            if (!$this->hasForeignKeyForColumn($table, 'nature_stock_id')) {
                $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E037FB6CC400 FOREIGN KEY (nature_stock_id) REFERENCES nature_production (id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('article')) {
            $table = $schema->getTable('article');

            if ($this->hasForeignKeyForColumn($table, 'fournisseur_habituel_id')) {
                $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E669F8E1010');
            }
            if ($this->hasForeignKeyForColumn($table, 'nature_production_id')) {
                $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E666CCF1BA6');
            }
            if ($table->hasIndex('IDX_23A0E669F8E1010')) {
                $this->addSql('DROP INDEX IDX_23A0E669F8E1010 ON article');
            }
            if ($table->hasIndex('IDX_23A0E666CCF1BA6')) {
                $this->addSql('DROP INDEX IDX_23A0E666CCF1BA6 ON article');
            }

            $dropColumns = [];
            foreach (['nature_production_id', 'fournisseur_habituel_id', 'mode_gestion', 'mode_suivi', 'sorti_stock'] as $column) {
                if ($table->hasColumn($column)) {
                    $dropColumns[] = 'DROP ' . $column;
                }
            }
            if ($dropColumns !== []) {
                $this->addSql('ALTER TABLE article ' . implode(', ', $dropColumns));
            }
        }

        if ($schema->hasTable('dossier')) {
            $table = $schema->getTable('dossier');

            if ($this->hasForeignKeyForColumn($table, 'nature_stock_id')) {
                $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E037FB6CC400');
            }
            if ($table->hasIndex('IDX_3D48E037FB6CC400')) {
                $this->addSql('DROP INDEX IDX_3D48E037FB6CC400 ON dossier');
            }

            $dropColumns = [];
            foreach (['nature_stock_id', 'sorti_stock_defaut', 'gerer_stocks', 'autoriser_stock_negatif'] as $column) {
                if ($table->hasColumn($column)) {
                    $dropColumns[] = 'DROP ' . $column;
                }
            }
            if ($dropColumns !== []) {
                $this->addSql('ALTER TABLE dossier ' . implode(', ', $dropColumns));
            }
        }

        if ($schema->hasTable('depot')) {
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBC611C0C56');
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBC433437B');
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBCA73F0036');
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBCA6E44244');
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBCB03A8386');
            $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBC880496E7');
            $this->addSql('DROP TABLE depot');
        }

        if ($schema->hasTable('tiers_interne')) {
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4E611C0C56');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4EA73F0036');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4EA6E44244');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4E357C0A59');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4E6A477111');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4EB03A8386');
            $this->addSql('ALTER TABLE tiers_interne DROP FOREIGN KEY FK_EB7C3E4E880496E7');
            $this->addSql('DROP TABLE tiers_interne');
        }

        if ($schema->hasTable('nature_production')) {
            $this->addSql('ALTER TABLE nature_production DROP FOREIGN KEY FK_3F930277B03A8386');
            $this->addSql('ALTER TABLE nature_production DROP FOREIGN KEY FK_3F930277880496E7');
            $this->addSql('DROP TABLE nature_production');
        }
    }

    private function hasForeignKeyForColumn(Table $table, string $column): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if (in_array($column, $foreignKey->getLocalColumns(), true)) {
                return true;
            }
        }

        return false;
    }
}
