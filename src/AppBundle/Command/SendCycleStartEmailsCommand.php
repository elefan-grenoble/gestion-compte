<?php
// src/AppBundle/Command/SendCycleStartEmailsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendCycleStartEmailsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:send_cycle_start_emails')
            ->setDescription('Send emails to member with a cycle starting today and with shift remaining to book')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = new \DateTime('now');

        $count = 0;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $users = $em->getRepository('AppBundle:User')->findFirstShiftWithUserNotInitialized();
        foreach ($users as $user) {
            if ($user->remainingToBook(1, true) > 0) {




                $firstDate = clone($shift->getStart());
                $firstDate->setTime(0, 0, 0);
                $user->setFirstShiftDate($firstDate);
                $em->persist($user);
                $count++;
            }
        }
        $em->flush();
        $message = $count . ' membre' . (($count > 1) ? 's' : '') . ' mis à jour';
        $output->writeln($message);
    }

}