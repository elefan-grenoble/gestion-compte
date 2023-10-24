<?php
// src/AppBundle/Command/ShiftGenerateCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use AppBundle\Event\ShiftReminderEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShiftReminderCommand extends ContainerAwareCommand
{
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
        $em = $this->getContainer()->get('doctrine')->getManager();

        $from_given = $input->getArgument('date');
        $from = date_create_from_format('Y-m-d',$from_given);
        if (!$from || $from->format('Y-m-d') != $from_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $output->writeln('<fg=cyan;>'.$from->format('d M Y').'</>');

        $qb = $em->getRepository('AppBundle:Shift')->createQueryBuilder('s')
            ->where('s.start >= :start')
            ->andWhere('s.end < :end')
            ->setParameter('start', $from->format('Y-m-d'))
            ->setParameter('end', $from->add(\DateInterval::createFromDateString('+1 day'))->format('Y-m-d'));

        $shifts = $qb->getQuery()->getResult();

        $message = 'Shift reminder for ' . count($shifts) . ' créneau' . ((count($shifts)>1) ? 'x':'');
        $output->writeln('<fg=cyan;>'.$message.'</>');

        $count_reminder_sent = 0;
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        foreach ($shifts as $shift) {
            if ($shift->getShifter()) {
                $dispatcher->dispatch(ShiftReminderEvent::NAME, new ShiftReminderEvent($shift));
                $count_reminder_sent++;
            }
        }

        $message = $count_reminder_sent . ' email' . (($count_reminder_sent>1) ? 's':'') . ' envoyé' . (($count_reminder_sent>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
    }
}
