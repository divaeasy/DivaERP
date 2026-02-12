<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260126142652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_23A0E66B03A8386 ON article (created_by_id)');
        $this->addSql('CREATE INDEX IDX_23A0E66880496E7 ON article (modifed_by_id)');
        $this->addSql('ALTER TABLE devises ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE devises ADD CONSTRAINT FK_D21EDEAB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE devises ADD CONSTRAINT FK_D21EDEA880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_D21EDEAB03A8386 ON devises (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D21EDEA880496E7 ON devises (modifed_by_id)');
        $this->addSql('ALTER TABLE entetepiece ADD dossier_id INT NOT NULL, ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD montant DOUBLE PRECISION DEFAULT NULL, ADD datep DATE DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_729815BC611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_729815BCB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE entetepiece ADD CONSTRAINT FK_729815BC880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_729815BC611C0C56 ON entetepiece (dossier_id)');
        $this->addSql('CREATE INDEX IDX_729815BCB03A8386 ON entetepiece (created_by_id)');
        $this->addSql('CREATE INDEX IDX_729815BC880496E7 ON entetepiece (modifed_by_id)');
        $this->addSql('ALTER TABLE lignepiece ADD dossier_id INT NOT NULL, ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lignepiece ADD CONSTRAINT FK_3E747BA611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE lignepiece ADD CONSTRAINT FK_3E747BAB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lignepiece ADD CONSTRAINT FK_3E747BA880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_3E747BA611C0C56 ON lignepiece (dossier_id)');
        $this->addSql('CREATE INDEX IDX_3E747BAB03A8386 ON lignepiece (created_by_id)');
        $this->addSql('CREATE INDEX IDX_3E747BA880496E7 ON lignepiece (modifed_by_id)');
        $this->addSql('ALTER TABLE pays ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE pays ADD CONSTRAINT FK_349F3CAEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pays ADD CONSTRAINT FK_349F3CAE880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_349F3CAEB03A8386 ON pays (created_by_id)');
        $this->addSql('CREATE INDEX IDX_349F3CAE880496E7 ON pays (modifed_by_id)');
        $this->addSql('ALTER TABLE prospects ADD dossier_id INT NOT NULL, ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE prospects ADD CONSTRAINT FK_35730C06611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE prospects ADD CONSTRAINT FK_35730C06B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE prospects ADD CONSTRAINT FK_35730C06880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_35730C06611C0C56 ON prospects (dossier_id)');
        $this->addSql('CREATE INDEX IDX_35730C06B03A8386 ON prospects (created_by_id)');
        $this->addSql('CREATE INDEX IDX_35730C06880496E7 ON prospects (modifed_by_id)');
        $this->addSql('ALTER TABLE reglement ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reglement ADD CONSTRAINT FK_EBE4C14CB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reglement ADD CONSTRAINT FK_EBE4C14C880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_EBE4C14CB03A8386 ON reglement (created_by_id)');
        $this->addSql('CREATE INDEX IDX_EBE4C14C880496E7 ON reglement (modifed_by_id)');        
        $this->addSql('ALTER TABLE tarifs ADD dossier_id INT NOT NULL, ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tarifs ADD CONSTRAINT FK_F9B8C496611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE tarifs ADD CONSTRAINT FK_F9B8C496B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tarifs ADD CONSTRAINT FK_F9B8C496880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_F9B8C496611C0C56 ON tarifs (dossier_id)');
        $this->addSql('CREATE INDEX IDX_F9B8C496B03A8386 ON tarifs (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F9B8C496880496E7 ON tarifs (modifed_by_id)');
        $this->addSql('ALTER TABLE tarifvente ADD dossier_id INT NOT NULL, ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tarifvente ADD CONSTRAINT FK_376E76BE611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE tarifvente ADD CONSTRAINT FK_376E76BEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tarifvente ADD CONSTRAINT FK_376E76BE880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_376E76BE611C0C56 ON tarifvente (dossier_id)');
        $this->addSql('CREATE INDEX IDX_376E76BEB03A8386 ON tarifvente (created_by_id)');
        $this->addSql('CREATE INDEX IDX_376E76BE880496E7 ON tarifvente (modifed_by_id)');
        $this->addSql('ALTER TABLE unite ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE unite ADD CONSTRAINT FK_1D64C118B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE unite ADD CONSTRAINT FK_1D64C118880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');        
        $this->addSql('CREATE INDEX IDX_1D64C118B03A8386 ON unite (created_by_id)');
        $this->addSql('CREATE INDEX IDX_1D64C118880496E7 ON unite (modifed_by_id)');        
        $this->addSql('ALTER TABLE ville ADD created_by_id INT DEFAULT NULL, ADD modifed_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ville ADD CONSTRAINT FK_43C3D9C3B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ville ADD CONSTRAINT FK_43C3D9C3880496E7 FOREIGN KEY (modifed_by_id) REFERENCES user (id)');        
        $this->addSql('CREATE INDEX IDX_43C3D9C3B03A8386 ON ville (created_by_id)');
        $this->addSql('CREATE INDEX IDX_43C3D9C3880496E7 ON ville (modifed_by_id)');        
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66B03A8386');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66880496E7');
        $this->addSql('DROP INDEX IDX_23A0E66B03A8386 ON article');
        $this->addSql('DROP INDEX IDX_23A0E66880496E7 ON article');
        $this->addSql('ALTER TABLE article DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE devises DROP FOREIGN KEY FK_D21EDEAB03A8386');
        $this->addSql('ALTER TABLE devises DROP FOREIGN KEY FK_D21EDEA880496E7');
        $this->addSql('ALTER TABLE devises DROP FOREIGN KEY FK_D21EDEA611C0C56');
        $this->addSql('DROP INDEX IDX_D21EDEAB03A8386 ON devises');
        $this->addSql('DROP INDEX IDX_D21EDEA880496E7 ON devises');
        $this->addSql('DROP INDEX IDX_D21EDEA611C0C56 ON devises');
        $this->addSql('ALTER TABLE devises DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE entetepiece DROP FOREIGN KEY FK_729815BC611C0C56');
        $this->addSql('ALTER TABLE entetepiece DROP FOREIGN KEY FK_729815BCB03A8386');
        $this->addSql('ALTER TABLE entetepiece DROP FOREIGN KEY FK_729815BC880496E7');
        $this->addSql('DROP INDEX IDX_729815BC611C0C56 ON entetepiece');
        $this->addSql('DROP INDEX IDX_729815BCB03A8386 ON entetepiece');
        $this->addSql('DROP INDEX IDX_729815BC880496E7 ON entetepiece');
        $this->addSql('ALTER TABLE entetepiece DROP dossier_id, DROP created_by_id, DROP modifed_by_id, DROP montant, DROP datep, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE lignepiece DROP FOREIGN KEY FK_3E747BA611C0C56');
        $this->addSql('ALTER TABLE lignepiece DROP FOREIGN KEY FK_3E747BAB03A8386');
        $this->addSql('ALTER TABLE lignepiece DROP FOREIGN KEY FK_3E747BA880496E7');
        $this->addSql('DROP INDEX IDX_3E747BA611C0C56 ON lignepiece');
        $this->addSql('DROP INDEX IDX_3E747BAB03A8386 ON lignepiece');
        $this->addSql('DROP INDEX IDX_3E747BA880496E7 ON lignepiece');
        $this->addSql('ALTER TABLE lignepiece DROP dossier_id, DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE pays DROP FOREIGN KEY FK_349F3CAEB03A8386');
        $this->addSql('ALTER TABLE pays DROP FOREIGN KEY FK_349F3CAE880496E7');
        $this->addSql('ALTER TABLE pays DROP FOREIGN KEY FK_349F3CAE611C0C56');
        $this->addSql('DROP INDEX IDX_349F3CAEB03A8386 ON pays');
        $this->addSql('DROP INDEX IDX_349F3CAE880496E7 ON pays');
        $this->addSql('DROP INDEX IDX_349F3CAE611C0C56 ON pays');
        $this->addSql('ALTER TABLE pays DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE prospects DROP FOREIGN KEY FK_35730C06611C0C56');
        $this->addSql('ALTER TABLE prospects DROP FOREIGN KEY FK_35730C06B03A8386');
        $this->addSql('ALTER TABLE prospects DROP FOREIGN KEY FK_35730C06880496E7');
        $this->addSql('DROP INDEX IDX_35730C06611C0C56 ON prospects');
        $this->addSql('DROP INDEX IDX_35730C06B03A8386 ON prospects');
        $this->addSql('DROP INDEX IDX_35730C06880496E7 ON prospects');
        $this->addSql('ALTER TABLE prospects DROP dossier_id, DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14CB03A8386');
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14C880496E7');
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14C611C0C56');
        $this->addSql('DROP INDEX IDX_EBE4C14CB03A8386 ON reglement');
        $this->addSql('DROP INDEX IDX_EBE4C14C880496E7 ON reglement');
        $this->addSql('DROP INDEX IDX_EBE4C14C611C0C56 ON reglement');
        $this->addSql('ALTER TABLE reglement DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE tarifs DROP FOREIGN KEY FK_F9B8C496611C0C56');
        $this->addSql('ALTER TABLE tarifs DROP FOREIGN KEY FK_F9B8C496B03A8386');
        $this->addSql('ALTER TABLE tarifs DROP FOREIGN KEY FK_F9B8C496880496E7');
        $this->addSql('DROP INDEX IDX_F9B8C496611C0C56 ON tarifs');
        $this->addSql('DROP INDEX IDX_F9B8C496B03A8386 ON tarifs');
        $this->addSql('DROP INDEX IDX_F9B8C496880496E7 ON tarifs');
        $this->addSql('ALTER TABLE tarifs DROP dossier_id, DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE tarifvente DROP FOREIGN KEY FK_376E76BE611C0C56');
        $this->addSql('ALTER TABLE tarifvente DROP FOREIGN KEY FK_376E76BEB03A8386');
        $this->addSql('ALTER TABLE tarifvente DROP FOREIGN KEY FK_376E76BE880496E7');
        $this->addSql('DROP INDEX IDX_376E76BE611C0C56 ON tarifvente');
        $this->addSql('DROP INDEX IDX_376E76BEB03A8386 ON tarifvente');
        $this->addSql('DROP INDEX IDX_376E76BE880496E7 ON tarifvente');
        $this->addSql('ALTER TABLE tarifvente DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE unite DROP FOREIGN KEY FK_1D64C118B03A8386');
        $this->addSql('ALTER TABLE unite DROP FOREIGN KEY FK_1D64C118880496E7');
        $this->addSql('ALTER TABLE unite DROP FOREIGN KEY FK_1D64C118611C0C56');
        $this->addSql('DROP INDEX IDX_1D64C118B03A8386 ON unite');
        $this->addSql('DROP INDEX IDX_1D64C118880496E7 ON unite');
        $this->addSql('DROP INDEX IDX_1D64C118611C0C56 ON unite');
        $this->addSql('ALTER TABLE unite DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE ville DROP FOREIGN KEY FK_43C3D9C3B03A8386');
        $this->addSql('ALTER TABLE ville DROP FOREIGN KEY FK_43C3D9C3880496E7');
        $this->addSql('ALTER TABLE ville DROP FOREIGN KEY FK_43C3D9C3611C0C56');
        $this->addSql('DROP INDEX IDX_43C3D9C3B03A8386 ON ville');
        $this->addSql('DROP INDEX IDX_43C3D9C3880496E7 ON ville');
        $this->addSql('DROP INDEX IDX_43C3D9C3611C0C56 ON ville');
        $this->addSql('ALTER TABLE ville DROP created_by_id, DROP modifed_by_id, DROP created_at, DROP updated_at');
    }
}
