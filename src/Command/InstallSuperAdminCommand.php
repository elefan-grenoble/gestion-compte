<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Creates the super admin of a fresh install, from SUPER_ADMIN_USERNAME and
 * SUPER_ADMIN_INITIAL_PASSWORD. Replaces the anonymous web bootstrap of
 * /user/install_admin, which the first visitor of a fresh instance could use.
 */
class InstallSuperAdminCommand extends Command
{
    /** Initial passwords that are never accepted: the .env.dist placeholder and well-known defaults. */
    private const REFUSED_PASSWORDS = ['', '<change-me>', 'password', 'changeme', 'admin'];

    private $em;
    private $params;

    public function __construct(EntityManagerInterface $em, ContainerBagInterface $params)
    {
        $this->em = $em;
        $this->params = $params;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:user:install_super_admin')
            ->setDescription('Create the super admin of a fresh install')
            ->setHelp('Creates the super admin from SUPER_ADMIN_USERNAME and SUPER_ADMIN_INITIAL_PASSWORD, unless a super admin already exists. Change its password at the first login.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (count($this->em->getRepository(User::class)->findByRole('ROLE_SUPER_ADMIN')) > 0) {
            $output->writeln('<fg=red;>A super admin already exists: nothing created.</>');

            return 1;
        }

        $password = (string) $this->params->get('super_admin.initial_password');
        if (in_array(strtolower(trim($password)), self::REFUSED_PASSWORDS, true)) {
            $output->writeln('<fg=red;>Set SUPER_ADMIN_INITIAL_PASSWORD to a password of your own first: nothing created.</>');

            return 2;
        }

        $admin = new User();
        $admin->setEmail($this->params->get('emails.admin')['address']);
        $admin->setPlainPassword($password);
        $admin->setUsername($this->params->get('super_admin.username'));
        $admin->setEnabled(true);
        $admin->addRole('ROLE_SUPER_ADMIN');
        $this->em->persist($admin);
        $this->em->flush();

        $output->writeln('<fg=green;>Super admin "' . $admin->getUsername() . '" created. Change its password at the first login.</>');

        return 0;
    }
}
