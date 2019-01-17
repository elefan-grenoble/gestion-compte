<?php
namespace AppBundle\Command;

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
        $date = new \DateTime('now');
        $date->modify('-'.$delay);

        $em = $this->getContainer()->get('doctrine')->getManager();

        $members = $em->getRepository('AppBundle:Membership')->findWithExpiredRegistrationFrom($date);
        $count = 0;
        foreach ($members as $member) {
            $member->setWithdrawn(true);
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