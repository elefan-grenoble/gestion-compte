<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190111112008 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE dynamic_content (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(64) NOT NULL, description LONGTEXT NOT NULL, content LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');

        $this->addSql("INSERT INTO dynamic_content (code, name, description, content) VALUES ('CARD_READER', 'Lecteur de cartes membres', 'Contenu affiché dans la colonne de droite sur le lecteur de cartes de membres', '')");
        $this->addSql("INSERT INTO dynamic_content (code, name, description, content) VALUES ('SHIFT_REMINDER_EMAIL', 'Email de rappel de créneau', 'Contenu inséré à la fin de l\'email de rappel de créneau', '')");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE dynamic_content');
    }
}
