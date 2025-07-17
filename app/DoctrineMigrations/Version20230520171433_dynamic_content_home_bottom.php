<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230520171433 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add new dynamic content to be included in home page (bottom)';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO dynamic_content (code, name, description, content, created_at, updated_at) VALUES ('HOME_BOTTOM', 'Page d\'accueil (en bas)', 'Bandeau du bas sur la page d\'accueil d\'un membre connecté', '', NOW(), NOW())");
        $this->addSql("UPDATE dynamic_content SET code = 'HOME_TOP', name = 'Page d\'accueil (en haut)', description = 'Bandeau du haut sur la page d\'accueil d\'un membre connecté' WHERE code = 'HOME'");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM dynamic_content WHERE code = 'HOME_BOTTOM'");
        $this->addSql("UPDATE dynamic_content SET code = 'HOME', name = 'Page d\'accueil', description = 'Bandeau sur la page d\'accueil d\'un membre connect\u00e9' WHERE code = 'HOME_TOP'");
    }
}
