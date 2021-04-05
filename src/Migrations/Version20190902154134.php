<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190902154134 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE swipe_card CHANGE code code VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE access_token CHANGE token token VARCHAR(191) NOT NULL');
        $this->addSql('ALTER TABLE refresh_token CHANGE token token VARCHAR(191) NOT NULL');
        $this->addSql('ALTER TABLE anonymous_beneficiary CHANGE email email VARCHAR(191) NOT NULL');
        $this->addSql('ALTER TABLE job CHANGE name name VARCHAR(191) NOT NULL');
        $this->addSql('ALTER TABLE auth_code CHANGE token token VARCHAR(191) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE access_token CHANGE token token VARCHAR(191) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE anonymous_beneficiary CHANGE email email VARCHAR(191) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE auth_code CHANGE token token VARCHAR(191) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE job CHANGE name name VARCHAR(191) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE refresh_token CHANGE token token VARCHAR(191) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE swipe_card CHANGE code code VARCHAR(50) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
    }
}
