<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221117160309 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE dynamic_content ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dynamic_content ADD CONSTRAINT FK_20B9DEB2B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE dynamic_content ADD CONSTRAINT FK_20B9DEB2896DBBDE FOREIGN KEY (updated_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX IDX_20B9DEB2B03A8386 ON dynamic_content (created_by_id)');
        $this->addSql('CREATE INDEX IDX_20B9DEB2896DBBDE ON dynamic_content (updated_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE dynamic_content DROP FOREIGN KEY FK_20B9DEB2B03A8386');
        $this->addSql('ALTER TABLE dynamic_content DROP FOREIGN KEY FK_20B9DEB2896DBBDE');
        $this->addSql('DROP INDEX IDX_20B9DEB2B03A8386 ON dynamic_content');
        $this->addSql('DROP INDEX IDX_20B9DEB2896DBBDE ON dynamic_content');
        $this->addSql('ALTER TABLE dynamic_content DROP created_by_id, DROP updated_by_id');
    }
}
