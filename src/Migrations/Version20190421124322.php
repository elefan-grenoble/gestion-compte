<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190421124322 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE process_update (id INT AUTO_INCREMENT NOT NULL, author_id INT DEFAULT NULL, date DATETIME NOT NULL, title VARCHAR(64) NOT NULL, description LONGTEXT NOT NULL, link VARCHAR(256) DEFAULT NULL, INDEX IDX_52C9ABDFF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE process_update ADD CONSTRAINT FK_52C9ABDFF675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template CHANGE description description VARCHAR(512) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE process_update');
        $this->addSql('ALTER TABLE email_template CHANGE description description VARCHAR(512) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
