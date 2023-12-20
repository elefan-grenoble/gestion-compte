<?php

namespace App\Command;

use App\DataFixtures\Purger\CustomPurgerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomPurgerCommand extends ContainerAwareCommand
{

    protected static $defaultName = 'app:custom-purge';

    private $entityManager;
    private $purgerFactory;

    public function __construct(EntityManagerInterface $entityManager, CustomPurgerFactory $purgerFactory)
    {
        $this->entityManager = $entityManager;
        $this->purgerFactory = $purgerFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Purges the database with a custom purger.')
            ->setHelp('This command allows you to purge the database while excluding specific tables (migration_versions, dynamic_content)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $purger = $this->purgerFactory->createForEntityManager('default', $this->entityManager, []);
        $purger->purge();

        $output->writeln('Database purged successfully with custom purger.');
    }

}