<?php
// src/AppBundle/Command/FreeReservedShiftsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitUsersFirstShiftDate extends ContainerAwareCommand
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
        $shifts = $em->getRepository('AppBundle:Shift')->findFirstShiftWithUserNotInitialized();
        $last_user_id = null;
        foreach ($shifts as $shift) {
            $user = $shift->getShifter()->getUser();
            if ($user->getId() != $last_user_id) {
                $last_user_id = $user->getId();
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