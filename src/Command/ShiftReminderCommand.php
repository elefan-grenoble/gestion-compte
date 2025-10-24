<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Shift;
use App\Event\ShiftReminderEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShiftReminderCommand extends Command
{
    private $em;
    private $event_dispatcher;

    public function __construct(
        EntityManagerInterface $em,
        EventDispatcherInterface $event_dispatcher
    )
    {
        $this->em = $em;
        $this->event_dispatcher = $event_dispatcher;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:shift:reminder')
            ->setDescription('Send reminder for shifts')
            ->setHelp('This command sends email reminder for all shifter of date given in param')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from_given = $input->getArgument('date');
        $from = date_create_from_format('Y-m-d',$from_given);
        if (!$from || $from->format('Y-m-d') != $from_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return 2;
        }
        $output->writeln('<fg=cyan;>'.$from->format('d M Y').'</>');

        $qb = $this->em->getRepository('App:Shift')->createQueryBuilder('s')
            ->where('s.start >= :start')
            ->andWhere('s.end < :end')
            ->setParameter('start', $from->format('Y-m-d'))
            ->setParameter('end', $from->add(\DateInterval::createFromDateString('+1 day'))->format('Y-m-d'));

        $shifts = $qb->getQuery()->getResult();

        $message = 'Shift reminder for ' . count($shifts) . ' créneau' . ((count($shifts)>1) ? 'x':'');
        $output->writeln('<fg=cyan;>'.$message.'</>');

        $count_reminder_sent = 0;
        foreach ($shifts as $shift) {
            if ($shift->getShifter()) {
                $this->event_dispatcher->dispatch(ShiftReminderEvent::NAME, new ShiftReminderEvent($shift));
                $count_reminder_sent++;
            }
        }

        $message = $count_reminder_sent . ' email' . (($count_reminder_sent>1) ? 's':'') . ' envoyé' . (($count_reminder_sent>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');

        return 0;
    }
}
