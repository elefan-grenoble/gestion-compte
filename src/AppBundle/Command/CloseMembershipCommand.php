<?php
namespace AppBundle\Command;

use AppBundle\Entity\Membership;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class CloseMembershipCommand extends ContainerAwareCommand
{
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

        $registration_every_civil_year = $this->getContainer()->getParameter('registration_every_civil_year');
        $date = new \DateTime('now');
        if ($registration_every_civil_year) {
            $date->modify('-1 year');
            $date->modify('-'.$delay);
            $date = new \DateTime('last day of December '.$date->format('Y'));
        } else {
            $registration_duration = \DateInterval::createFromDateString(
                $this->getContainer()->getParameter('registration_duration'));
            $date->sub($registration_duration);
            $date->modify('-1 day');
            $date->modify('-'.$delay);
        }

        $em = $this->getContainer()->get('doctrine')->getManager();

        $members = $em->getRepository('AppBundle:Membership')->findWithExpiredRegistrationFrom($date);
        $count = 0;
        /** @var Membership $member */
        foreach ($members as $member) {
            $member->setWithdrawn(true);
            $member->setFrozen(false); //not frozen anymore
            $em->persist($member);
            $count++;
            $message = 'Close membership #' . $member->getMemberNumber();
            $output->writeln($message);
        }

        $em->flush();

        $message = $count . ' membership(s) closed';
        $output->writeln($message);
    }

}
