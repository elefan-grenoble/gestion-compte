<?php

namespace App\Command;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\TimeLog;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixTimeLogCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:fix_time_log')
            ->setDescription('Fix time logs data')
            ->setHelp('This command allows you to fix time logs data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $members = $em->getRepository('App:Membership')->findAll();

        $countShiftLogs = 0;

        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {
                $previous_cycle_start = $this->getContainer()->get('membership_service')->getStartOfCycle($member, -1);
                $current_cycle_end = $this->getContainer()->get('membership_service')->getEndOfCycle($member, 0);
                $shifts = $em->getRepository('App:Shift')->findShiftsForMembership($member, $previous_cycle_start, $current_cycle_end);

                foreach ($shifts as $shift) {
                    $logs = $member->getTimeLogs()->filter(function ($log) use ($shift) {
                        return ($log->getShift() && $log->getShift()->getId() == $shift->getId());
                    });
                    // Insert log if it doesn't exist fot this shift
                    if ($logs->count() == 0) {
                        $log = $this->getContainer()->get('time_log_service')->initShiftValidatedTimeLog($shift, $shift->getStart(), "Créneau réalisé");
                        $em->persist($log);
                        $countShiftLogs++;
                    }
                }
            }
        }

        $em->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
    }
}
