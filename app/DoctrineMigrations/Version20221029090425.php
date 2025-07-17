<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221029090425 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4968B7E4006');
        $this->addSql('UPDATE period_position LEFT OUTER JOIN beneficiary ON beneficiary.id = period_position.booker_id SET booker_id=beneficiary.user_id');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D4968B7E4006 FOREIGN KEY (booker_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4968B7E4006');
        $this->addSql('UPDATE period_position LEFT OUTER JOIN beneficiary ON beneficiary.user_id = period_position.booker_id SET booker_id=beneficiary.id');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D4968B7E4006 FOREIGN KEY (booker_id) REFERENCES beneficiary (id)');
    }
}
