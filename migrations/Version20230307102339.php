<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230307102339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE VOLUME ADD titles JSON DEFAULT NULL, ADD descriptions JSON DEFAULT NULL, CHANGE POSITION POSITION INT UNSIGNED NOT NULL, CHANGE BIB_REFERENCE BIB_REFERENCE VARCHAR(255) DEFAULT NULL COMMENT \'Volume\'\'s bibliographical reference\''
        );
        $this->addSql('ALTER TABLE VOLUME_SETTING CHANGE VALUE VALUE TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE VOLUME_METADATA ADD titles JSON DEFAULT NULL, CHANGE CONTENT CONTENT JSON NOT NULL');

        $this->addSql('ALTER TABLE VOLUME_SETTING ADD CONSTRAINT FK_E61584ABC77AFAD5 FOREIGN KEY (VID) REFERENCES VOLUME (VID)');
        $this->addSql('ALTER TABLE VOLUME_METADATA ADD CONSTRAINT FK_CBD314F6C77AFAD5 FOREIGN KEY (VID) REFERENCES VOLUME (VID)');
        $this->addSql('ALTER TABLE VOLUME_SETTING CHANGE VALUE VALUE TEXT DEFAULT NULL');
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refreshToken VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E16973EC66 (refreshToken), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_tokens');

    }
}
