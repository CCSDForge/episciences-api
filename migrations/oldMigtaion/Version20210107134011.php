<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210107134011 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_1F6CA7BCC53C448C ON PAPERS (UID)');
        $this->addSql('ALTER TABLE PAPER_RATING_GRID CHANGE DOCID DOCID INT NOT NULL');
        $this->addSql('ALTER TABLE USER_INVITATION_ANSWER_DETAIL CHANGE ID ID INT UNSIGNED NOT NULL COMMENT \'Invitation ID\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE PAPERS DROP FOREIGN KEY FK_1F6CA7BCC53C448C');
        $this->addSql('DROP INDEX IDX_1F6CA7BCC53C448C ON PAPERS');
        $this->addSql('ALTER TABLE PAPER_RATING_GRID CHANGE DOCID DOCID INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE USER_INVITATION_ANSWER_DETAIL CHANGE ID ID INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Invitation ID\'');
    }
}
