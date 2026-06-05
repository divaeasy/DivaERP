<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make code_operation_id nullable in entetepiece table to allow testing migration logic with test data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entetepiece CHANGE code_operation_id code_operation_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entetepiece CHANGE code_operation_id code_operation_id INT NOT NULL');
    }
}
