<?php declare(strict_types=1);

namespace App\Migrations;

use App\Entity\User;
use App\EventListener\SetFirstPasswordListener;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190402014558_add_role_to_never_logged_user extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function up(Schema $schema) : void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        // this up() migration is auto-generated, please modify it to your needs
        $query = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.lastLogin IS NULL')
            ->getQuery();

        $users = $query->getResult();

        foreach ($users as $user){
            $user->addRole(SetFirstPasswordListener::ROLE_PASSWORD_TO_SET);
            $em->persist($user);
        }
        $em->flush();
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $em = $this->container->get('doctrine.orm.entity_manager');
        // this up() migration is auto-generated, please modify it to your needs
        $query = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.lastLogin IS NULL')
            ->getQuery();

        $users = $query->getResult();

        foreach ($users as $user){
            $user->removeRole(SetFirstPasswordListener::ROLE_PASSWORD_TO_SET);
            $em->persist($user);
        }
        $em->flush();
    }
}
