<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230519151558 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add new dynamic content to be included in pre-membership email';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO dynamic_content (code, name, description, content, created_at, updated_at) VALUES ('PRE_MEMBERSHIP_EMAIL', 'Email de pré-adhésion', 'Contenu du mail de pré-adhésion', '', NOW(), NOW())");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM dynamic_content WHERE code = 'PRE_MEMBERSHIP_EMAIL'");
    }
}
