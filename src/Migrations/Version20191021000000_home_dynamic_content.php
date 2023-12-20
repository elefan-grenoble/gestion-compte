<?php declare(strict_types=1);

namespace app\Migrations;

use App\Entity\DynamicContent;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191021000000_home_dynamic_content extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO dynamic_content (code, name, description, content) VALUES ('HOME', 'Page d\'accueil', 'Bandeau sur la page d\'accueil d\'un membre connect\u00e9', '')");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM dynamic_content WHERE code = 'HOME'");
    }
}
