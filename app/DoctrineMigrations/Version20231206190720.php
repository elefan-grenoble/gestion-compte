<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231206190720 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE code ADD codedevice_id INT DEFAULT NULL, ADD description VARCHAR(255) DEFAULT NULL, ADD start_date DATETIME DEFAULT NULL, ADD end_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE code ADD CONSTRAINT FK_7715309843E5FA9F FOREIGN KEY (codedevice_id) REFERENCES code_device (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_7715309843E5FA9F ON code (codedevice_id)');
        if($this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM code);'))
        {
            $this->addSql('INSERT INTO code_device (name, description, type, enabled, created_at, updated_at) VALUES ("Boite à clés", "A modifier", "other", 1, NOW(), NOW())');
            $this->addSql('UPDATE code SET codedevice_id=(SELECT id from code_device LIMIT 1)');
        }
        $this->addSql('ALTER TABLE code MODIFY codedevice_id INT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE code DROP FOREIGN KEY FK_7715309843E5FA9F');
        $this->addSql('DROP INDEX IDX_7715309843E5FA9F ON code');
        $this->addSql('ALTER TABLE code DROP codedevice_id, DROP description, DROP start_date, DROP end_date');
    }
}
