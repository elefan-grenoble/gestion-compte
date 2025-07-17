<?php
// src/AppBundle/Command/FreeReservedShiftsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Works only for coops with 'reserve_new_shift_to_prior_shifter' true.
 * Note: should be run 'reserve_new_shift_to_prior_shifter_delay' days after ShiftGenerateCommand.
 */
class FreeReservedShiftsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:free')
            ->setDescription('Free reserved shifts')
            ->setHelp('This command allows you to free reserved shifts for a given date')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reserve_new_shift_to_prior_shifter = $this->getContainer()->getParameter('reserve_new_shift_to_prior_shifter');
        if (!$reserve_new_shift_to_prior_shifter) {
            $output->writeln('<fg=red;> reserve_new_shift_to_prior_shifter parameter must be true </>');
            return;
        }

        $date_given = $input->getArgument('date');
        $date = date_create_from_format('Y-m-d',$date_given);
        if (!$date || $date->format('Y-m-d') != $date_given){
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $date->setTime(0,0);
        $output->writeln('<fg=cyan;>'.$date->format('d M Y').'</>');

        $count = 0;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findReservedAt($date);
        foreach ($shifts as $shift) {
            $shift->setLastShifter(null);
            $em->persist($shift);
            $count++;
        }
        $em->flush();

        $message = $count.' créneau'.(($count>1) ? 'x':'').' libéré'.(($count>1) ? 's':'');
        $output->writeln($message);
    }
}
