<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200229132018 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove frozen flag from closed (withdrawn) account';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('UPDATE membership SET frozen = "0" WHERE withdrawn = "1" AND frozen = "1"');
    }

    public function down(Schema $schema) : void
    {
        // Wont be reversible
    }
}
