<?php
// src/App/Command/InitUsersFirstShiftDateCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitUsersFirstShiftDateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:init_first_shift_date')
            ->setDescription('Init first_shift_date of users')
            ->setHelp('This command allows you to init users first shift date');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $count = 0;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('App:Shift')->findFirstShiftWithUserNotInitialized();
        $last_member_id = null;
        foreach ($shifts as $shift) {
            $membership = $shift->getShifter()->getMembership();
            if ($membership->getId() != $last_member_id) {
                $last_member_id = $membership->getId();
                $firstDate = clone($shift->getStart());
                $firstDate->setTime(0, 0, 0);
                $membership->setFirstShiftDate($firstDate);
                $em->persist($membership);
                $count++;
            }
        }
        $em->flush();
        $message = $count . ' membre' . (($count > 1) ? 's' : '') . ' mis à jour';
        $output->writeln($message);
    }

}