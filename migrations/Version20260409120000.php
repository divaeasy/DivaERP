<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional image path column to article table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('article')) {
            return;
        }

        $table = $schema->getTable('article');
        if (!$table->hasColumn('image')) {
            $this->addSql('ALTER TABLE article ADD image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('article')) {
            return;
        }

        $table = $schema->getTable('article');
        if ($table->hasColumn('image')) {
            $this->addSql('ALTER TABLE article DROP image');
        }
    }
}
