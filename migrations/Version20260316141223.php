<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316141223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing user.dossier_id foreign key and backfill from current dossier data';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');

        // 1) Add the missing column if it is not already there
        if (!$user->hasColumn('dossier_id')) {
            $this->addSql('ALTER TABLE user ADD dossier_id INT DEFAULT NULL');
        }

        // 2) Ensure an index exists for the FK column
        if (!$user->hasIndex('IDX_8D93D649611C0C56')) {
            $this->addSql('CREATE INDEX IDX_8D93D649611C0C56 ON user (dossier_id)');
        }

        // 3) Add the foreign key if missing
        $hasDossierFk = false;
        foreach ($user->getForeignKeys() as $fk) {
            if (in_array('dossier_id', $fk->getLocalColumns(), true)) {
                $hasDossierFk = true;
                break;
            }
        }

        if (!$hasDossierFk) {
            $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id) ON DELETE SET NULL');
        }

        // 4) Backfill dossier_id from existing data (current dossier first, then any accessible dossier)
        $this->addSql('UPDATE user SET dossier_id = current_dossier_id WHERE dossier_id IS NULL AND current_dossier_id IS NOT NULL');
        $this->addSql('UPDATE user u SET dossier_id = (SELECT d.dossier_id FROM user_dossier d WHERE d.user_id = u.id LIMIT 1) WHERE u.dossier_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');

        if ($user->hasColumn('dossier_id')) {
            // Drop FK and index only if they exist
            foreach ($user->getForeignKeys() as $fk) {
                if (in_array('dossier_id', $fk->getLocalColumns(), true)) {
                    $this->addSql(sprintf('ALTER TABLE user DROP FOREIGN KEY %s', $fk->getName()));
                }
            }

            if ($user->hasIndex('IDX_8D93D649611C0C56')) {
                $this->addSql('DROP INDEX IDX_8D93D649611C0C56 ON user');
            }

            $this->addSql('ALTER TABLE user DROP dossier_id');
        }
    }
}
