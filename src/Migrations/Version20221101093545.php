<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221101093545 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B458B7E4006');
        $this->addSql('UPDATE shift LEFT OUTER JOIN beneficiary ON beneficiary.id = shift.booker_id SET booker_id=beneficiary.user_id');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B458B7E4006 FOREIGN KEY (booker_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B458B7E4006');
        $this->addSql('UPDATE shift LEFT OUTER JOIN beneficiary ON beneficiary.user_id = shift.booker_id SET booker_id=beneficiary.id');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B458B7E4006 FOREIGN KEY (booker_id) REFERENCES beneficiary (id)');
    }
}
