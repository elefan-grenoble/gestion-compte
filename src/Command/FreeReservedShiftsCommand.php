<?php
// src/App/Command/FreeReservedShiftsCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Works only for coops with 'reserve_new_shift_to_prior_shifter' true.
 * Note: should be run 'reserve_new_shift_to_prior_shifter_delay' days after ShiftGenerateCommand.
 */
class FreeReservedShiftsCommand extends Command
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
            ->setName('app:shift:free')
            ->setDescription('Free reserved shifts')
            ->setHelp('This command allows you to free reserved shifts for a given date')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reserve_new_shift_to_prior_shifter = $this->params->get('reserve_new_shift_to_prior_shifter');
        if (!$reserve_new_shift_to_prior_shifter) {
            $output->writeln('<fg=red;> reserve_new_shift_to_prior_shifter parameter must be true </>');
            return 1;
        }

        $date_given = $input->getArgument('date');
        $date = date_create_from_format('Y-m-d',$date_given);
        if (!$date || $date->format('Y-m-d') != $date_given){
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return 2;
        }
        $date->setTime(0,0);
        $output->writeln('<fg=cyan;>'.$date->format('d M Y').'</>');

        $count = 0;
        $shifts = $this->em->getRepository('App:Shift')->findReservedAt($date);
        foreach ($shifts as $shift) {
            $shift->setLastShifter(null);
            $this->em->persist($shift);
            $count++;
        }
        $this->em->flush();

        $message = $count.' créneau'.(($count>1) ? 'x':'').' libéré'.(($count>1) ? 's':'');
        $output->writeln($message);

        return 0;
    }
}
