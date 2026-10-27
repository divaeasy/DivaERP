<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace entetepiece.client_id with entetepiece.tier_id and migrate existing Client pieces';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('entetepiece')) {
            return;
        }

        $table = $schema->getTable('entetepiece');
        if (!$table->hasColumn('tier_id')) {
            $this->addSql('ALTER TABLE entetepiece ADD tier_id INT DEFAULT NULL');
        }
        if (!$table->hasIndex('IDX_ENTETEPIECE_TIER_ID')) {
            $this->addSql('CREATE INDEX IDX_ENTETEPIECE_TIER_ID ON entetepiece (tier_id)');
        }

        if ($table->hasColumn('client_id')) {
            $this->addSql("UPDATE entetepiece SET tier_id = client_id WHERE tier_id IS NULL AND LOWER(COALESCE(typet, '')) = 'client'");

            foreach ($table->getForeignKeys() as $foreignKey) {
                if (in_array('client_id', $foreignKey->getLocalColumns(), true)) {
                    $this->addSql(sprintf('ALTER TABLE entetepiece DROP FOREIGN KEY %s', $foreignKey->getName()));
                }
            }

            foreach ($table->getIndexes() as $index) {
                if ($index->isPrimary()) {
                    continue;
                }

                $columns = array_map('strtolower', $index->getColumns());
                if (count($columns) === 1 && $columns[0] === 'client_id') {
                    $this->addSql(sprintf('DROP INDEX %s ON entetepiece', $index->getName()));
                }
            }

            $this->addSql('ALTER TABLE entetepiece DROP client_id');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('entetepiece')) {
            return;
        }

        $table = $schema->getTable('entetepiece');

        if (!$table->hasColumn('client_id')) {
            $this->addSql('ALTER TABLE entetepiece ADD client_id INT DEFAULT NULL');
        }
        if (!$table->hasIndex('IDX_ENTETEPIECE_CLIENT_ID')) {
            $this->addSql('CREATE INDEX IDX_ENTETEPIECE_CLIENT_ID ON entetepiece (client_id)');
        }

        $hasClientFk = false;
        foreach ($table->getForeignKeys() as $foreignKey) {
            if (in_array('client_id', $foreignKey->getLocalColumns(), true)) {
                $hasClientFk = true;
                break;
            }
        }
        if (!$hasClientFk) {
            $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_ENTETEPIECE_CLIENT FOREIGN KEY (client_id) REFERENCES clients (id)');
        }

        if ($table->hasColumn('tier_id')) {
            $this->addSql("UPDATE entetepiece SET client_id = tier_id WHERE client_id IS NULL AND LOWER(COALESCE(typet, '')) = 'client'");

            if ($table->hasIndex('IDX_ENTETEPIECE_TIER_ID')) {
                $this->addSql('DROP INDEX IDX_ENTETEPIECE_TIER_ID ON entetepiece');
            }

            $this->addSql('ALTER TABLE entetepiece DROP tier_id');
        }
    }
}
