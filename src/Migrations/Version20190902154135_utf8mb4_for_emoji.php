<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190902154135_utf8mb4_for_emoji extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER DATABASE '.$schema->getName().' CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;');

        $tables = array('access_token','address','anonymous_beneficiary','auth_code','beneficiaries_commissions','beneficiaries_formations',
                    'beneficiary','client','code','commission','dynamic_content','email_template','event','formation',
                    'fos_user','helloasso_payment','job','membership','migration_versions','note','period','period_position',
                    'period_position_period','process_update','proxy','refresh_token','registration','service','shift',
                    'swipe_card','swipe_card_log','task','tasks_beneficiaries','tasks_commissions','time_log','users_clients');

        foreach ($tables as $table){
            $this->addSql('ALTER TABLE '.$table.' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
