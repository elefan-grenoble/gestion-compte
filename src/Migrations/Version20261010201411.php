<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010201411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Nouvelle table pour stocker la configuration de l'application";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE setting (setting_key VARCHAR(255) NOT NULL, value VARCHAR(255) DEFAULT NULL, PRIMARY KEY(setting_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE setting');
    }
}
