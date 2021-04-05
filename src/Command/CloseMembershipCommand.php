<?php
namespace App\Command;

use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class CloseMembershipCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var string
     */
    private $registrationDuration;

    public function __construct(EntityManagerInterface $entityManager, string $registrationDuration)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->registrationDuration = $registrationDuration;
    }

    protected function configure()
    {
        $this
            ->setName('app:member:close')
            ->setDescription('Close memberships with unrenewed registration')
            ->setHelp('This command close memberships when the registration has not been renewed after a delay specified parameters')
            ->addArgument('delay', InputArgument::REQUIRED, "Delay (example: '1 month')");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<fg=green;>close accounts command</>');

        $delay = $input->getArgument('delay');
        $date = new \DateTime('now');
        $date->modify('-'.$delay);

        $delay = \DateInterval::createFromDateString($this->registrationDuration);
        $members = $this->entityManager->getRepository('App:Membership')->findWithExpiredRegistrationFrom($date,$delay->y);
        $count = 0;
        /** @var Membership $member */
        foreach ($members as $member) {
            $member->setWithdrawn(true);
            $member->setFrozen(false); //not frozen anymore
            $this->entityManager->persist($member);
            $count++;
            $message = 'Close membership #' . $member->getMemberNumber();
            $output->writeln($message);
        }

        $this->entityManager->flush();

        $message = $count . ' membership(s) closed';
        $output->writeln($message);
    }

}