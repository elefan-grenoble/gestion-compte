<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190324212024_fix_formations extends AbstractMigration
{

    public function up(Schema $schema) : void
    {
        $this->addSql("UPDATE formation SET roles = 'a:0:{}' WHERE roles LIKE 'N;'");
    }

    public function down(Schema $schema) : void
    {

    }
}
