<?php
namespace App\Command;

use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CloseMembershipCommand extends Command
{
    private $em;
    private $params;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params
    )
    {
        $this->em = $em;
        $this->params = $params;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:member:close')
            ->setDescription('Close memberships with unrenewed registration')
            ->setHelp('This command close memberships when the registration has not been renewed after a delay specified parameters')
            ->addArgument('delay', InputArgument::REQUIRED, "Delay (example: '1 month')");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=green;>close accounts command</>');

        $delay = $input->getArgument('delay');

        $registration_every_civil_year = $this->params->get('registration_every_civil_year');
        $date = new \DateTime('now');
        if ($registration_every_civil_year) {
            $date->modify('-1 year');
            $date->modify('-' . $delay);
            $date = new \DateTime('last day of December ' . $date->format('Y'));
        } else {
            $registration_duration = \DateInterval::createFromDateString($this->params->get('registration_duration'));
            $date->sub($registration_duration);
            $date->modify('-1 day');
            $date->modify('-' . $delay);
        }

        $members = $this->em->getRepository('App:Membership')->findWithExpiredRegistrationFrom($date);
        $count = 0;
        /** @var Membership $member */
        foreach ($members as $member) {
            $member->setWithdrawn(true);
            $member->setWithdrawnDate(new \DateTime('now'));
            // $member->setWithdrawnBy(); //TODO
            $member->setFrozen(false); //not frozen anymore
            $this->em->persist($member);
            $count++;
            $message = 'Close membership #' . $member->getMemberNumber();
            $output->writeln($message);
        }

        $this->em->flush();

        $message = $count . ' membership(s) closed';
        $output->writeln($message);

        return 0;
    }

}
