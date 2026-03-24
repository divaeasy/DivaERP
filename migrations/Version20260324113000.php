<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create theme table and migrate dossier.theme string to dossier.theme_id relation';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('theme')) {
            $this->addSql('CREATE TABLE theme (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(120) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                primary_color VARCHAR(7) NOT NULL,
                secondary_color VARCHAR(7) NOT NULL,
                accent_color VARCHAR(7) NOT NULL,
                text_color VARCHAR(7) NOT NULL,
                background_color VARCHAR(7) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE INDEX UNIQ_THEME_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $this->addSql("INSERT IGNORE INTO theme (name, code, description, primary_color, secondary_color, accent_color, text_color, background_color, is_system) VALUES
            ('Indigo', 'indigo', 'SaaS moderne', '#4F46E5', '#4338CA', '#818CF8', '#0F172A', '#F9FAFB', 1),
            ('Ardoise', 'ardoise', 'Neutre et epure', '#475569', '#334155', '#94A3B8', '#020617', '#F8FAFC', 1),
            ('Bleu corporate', 'bleu-corporate', 'Entreprise / ERP', '#1E40AF', '#1E3A8A', '#60A5FA', '#0F172A', '#F8FAFC', 1),
            ('Emeraude', 'emeraude', 'Performance et croissance', '#059669', '#047857', '#34D399', '#022C22', '#F0FDF4', 1),
            ('Minimal clair', 'minimal-clair', 'Epure et minimaliste', '#111827', '#374151', '#6B7280', '#111827', '#FFFFFF', 1)
        ");

        $dossier = $schema->getTable('dossier');

        if (!$dossier->hasColumn('theme_id')) {
            $this->addSql('ALTER TABLE dossier ADD theme_id INT DEFAULT NULL');
        }

        if (!$dossier->hasIndex('IDX_DOSSIER_THEME')) {
            $this->addSql('CREATE INDEX IDX_DOSSIER_THEME ON dossier (theme_id)');
        }

        $hasThemeFk = false;
        foreach ($dossier->getForeignKeys() as $foreignKey) {
            if (in_array('theme_id', $foreignKey->getLocalColumns(), true)) {
                $hasThemeFk = true;
                break;
            }
        }

        if (!$hasThemeFk) {
            $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_DOSSIER_THEME FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE SET NULL');
        }

        if ($dossier->hasColumn('theme')) {
            $this->addSql("UPDATE dossier d
                LEFT JOIN theme t ON t.code = (
                    CASE
                        WHEN LOWER(TRIM(COALESCE(d.theme, ''))) IN ('indigo', 'ocean') THEN 'indigo'
                        WHEN LOWER(TRIM(COALESCE(d.theme, ''))) IN ('ardoise', 'forest') THEN 'ardoise'
                        WHEN LOWER(TRIM(COALESCE(d.theme, ''))) IN ('bleu-corporate', 'sand', '#4e73df', '#224abe') THEN 'bleu-corporate'
                        WHEN LOWER(TRIM(COALESCE(d.theme, ''))) IN ('emeraude', 'night') THEN 'emeraude'
                        WHEN LOWER(TRIM(COALESCE(d.theme, ''))) IN ('minimal-clair', 'neutral') THEN 'minimal-clair'
                        ELSE 'indigo'
                    END
                )
                SET d.theme_id = t.id
                WHERE d.theme_id IS NULL");
        }

        $this->addSql("UPDATE dossier d
            INNER JOIN theme t ON t.code = 'indigo'
            SET d.theme_id = t.id
            WHERE d.theme_id IS NULL");

        if ($dossier->hasColumn('theme')) {
            $this->addSql('ALTER TABLE dossier DROP theme');
        }
    }

    public function down(Schema $schema): void
    {
        $dossier = $schema->getTable('dossier');

        if (!$dossier->hasColumn('theme')) {
            $this->addSql('ALTER TABLE dossier ADD theme VARCHAR(50) DEFAULT NULL');
        }

        if ($dossier->hasColumn('theme_id') && $schema->hasTable('theme')) {
            $this->addSql("UPDATE dossier d
                LEFT JOIN theme t ON t.id = d.theme_id
                SET d.theme = COALESCE(t.code, 'indigo')
                WHERE d.theme IS NULL OR d.theme = ''");
        }

        if ($dossier->hasColumn('theme_id')) {
            foreach ($dossier->getForeignKeys() as $foreignKey) {
                if (in_array('theme_id', $foreignKey->getLocalColumns(), true)) {
                    $this->addSql(sprintf('ALTER TABLE dossier DROP FOREIGN KEY %s', $foreignKey->getName()));
                }
            }

            if ($dossier->hasIndex('IDX_DOSSIER_THEME')) {
                $this->addSql('DROP INDEX IDX_DOSSIER_THEME ON dossier');
            }

            $this->addSql('ALTER TABLE dossier DROP theme_id');
        }

        if ($schema->hasTable('theme')) {
            $this->addSql('DROP TABLE theme');
        }
    }
}

