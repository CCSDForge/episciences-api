<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210112151933 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE PAPERS ADD CONSTRAINT FK_1F6CA7BC787B3BF2 FOREIGN KEY (RVID) REFERENCES REVIEW (RVID)');
        $this->addSql('ALTER TABLE PAPER_AUTHORS CHANGE AUTHORID AUTHORID INT UNSIGNED DEFAULT NULL, CHANGE UID UID INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE PAPER_METADATA CHANGE UID UID INT UNSIGNED NOT NULL COMMENT \'Par defaut UID = 0 : la méta est recupérée automatiquement. sinon : saisie manuelle, donc, on enregistre l\'\'UID de l\'\'utilisateur\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE PAPERS DROP FOREIGN KEY FK_1F6CA7BC787B3BF2');
        $this->addSql('ALTER TABLE PAPER_AUTHORS CHANGE UID UID INT UNSIGNED DEFAULT 0 NOT NULL, CHANGE AUTHORID AUTHORID INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE PAPER_METADATA CHANGE UID UID INT UNSIGNED DEFAULT 0 NOT NULL COMMENT \'Par defaut UID = 0 : la méta est recupérée automatiquement. sinon : saisie manuelle, donc, on enregistre l\'\'UID de l\'\'utilisateur\'');
    }
}
