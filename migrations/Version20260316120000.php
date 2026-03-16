<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add current dossier and dossier access mapping for users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD current_dossier_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_CURRENT_DOSSIER ON user (current_dossier_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_USER_CURRENT_DOSSIER FOREIGN KEY (current_dossier_id) REFERENCES dossier (id)');

        $this->addSql('CREATE TABLE user_dossier (user_id INT NOT NULL, dossier_id INT NOT NULL, INDEX IDX_USER_DOSSIER_USER (user_id), INDEX IDX_USER_DOSSIER_DOSSIER (dossier_id), PRIMARY KEY(user_id, dossier_id))');
        $this->addSql('ALTER TABLE user_dossier ADD CONSTRAINT FK_USER_DOSSIER_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dossier ADD CONSTRAINT FK_USER_DOSSIER_DOSSIER FOREIGN KEY (dossier_id) REFERENCES dossier (id) ON DELETE CASCADE');

        $this->addSql('UPDATE user SET current_dossier_id = dossier_id WHERE current_dossier_id IS NULL');
        $this->addSql('INSERT INTO user_dossier (user_id, dossier_id) SELECT id, dossier_id FROM user WHERE dossier_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_dossier DROP FOREIGN KEY FK_USER_DOSSIER_USER');
        $this->addSql('ALTER TABLE user_dossier DROP FOREIGN KEY FK_USER_DOSSIER_DOSSIER');
        $this->addSql('DROP TABLE user_dossier');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_USER_CURRENT_DOSSIER');
        $this->addSql('DROP INDEX IDX_USER_CURRENT_DOSSIER ON user');
        $this->addSql('ALTER TABLE user DROP current_dossier_id');
    }
}
