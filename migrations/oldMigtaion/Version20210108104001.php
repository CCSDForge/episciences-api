<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210108104001 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE USER ADD USERNAME VARCHAR(100) NOT NULL, ADD PASSWORD VARCHAR(255) NOT NULL, ADD EMAIL VARCHAR(320) NOT NULL, ADD CIV VARCHAR(255) DEFAULT NULL, ADD LASTNAME VARCHAR(100) NOT NULL, ADD FIRSTNAME VARCHAR(100) DEFAULT NULL, ADD TIME_REGISTERED DATETIME DEFAULT NULL, ADD TIME_MODIFIED DATETIME DEFAULT NULL, ADD VALID TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE USER DROP USERNAME, DROP PASSWORD, DROP EMAIL, DROP CIV, DROP LASTNAME, DROP FIRSTNAME, DROP TIME_REGISTERED, DROP TIME_MODIFIED, DROP VALID');
    }
}
