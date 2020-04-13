<?php
// src/App/Command/FreeReservedShiftsCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FreeReservedShiftsCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date_given = $input->getArgument('date');
        $date = date_create_from_format('Y-m-d',$date_given);
        if (!$date || $date->format('Y-m-d') != $date_given){
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $date->setTime(0,0);
        $output->writeln('<fg=cyan;>'.$date->format('d M Y').'</>');
        $count = 0;
        $shifts = $this->entityManager->getRepository('App:Shift')->findReservedAt($date);
        foreach ($shifts as $shift) {
            $shift->setLastShifter(null);
            $this->entityManager->persist($shift);
            $count++;
        }
        $this->entityManager->flush();
        $message = $count.' créneau'.(($count>1) ? 'x':'').' libéré'.(($count>1) ? 's':'');
        $output->writeln($message);
    }

}