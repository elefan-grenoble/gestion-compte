<?php

namespace App\Command;

use App\Entity\Membership;
use App\Service\MembershipService;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Service\TimeLogService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixTimeLogCommand extends Command
{
    private $em;
    private $membership_service;
    private $time_log_service;

    public function __construct(
        EntityManagerInterface $em,
        MembershipService $membership_service,
        TimeLogService $time_log_service
    )
    {
        $this->em = $em;
        $this->membership_service = $membership_service;
        $this->time_log_service = $time_log_service;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:user:fix_time_log')
            ->setDescription('Fix time logs data')
            ->setHelp('This command allows you to fix time logs data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $members = $this->em->getRepository('App:Membership')->findAll();

        $countShiftLogs = 0;

        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {
                $previous_cycle_start = $this->membership_service->getStartOfCycle($member, -1);
                $current_cycle_end = $this->membership_service->getEndOfCycle($member, 0);
                $shifts = $this->em->getRepository('App:Shift')->findShiftsForMembership($member, $previous_cycle_start, $current_cycle_end);

                foreach ($shifts as $shift) {
                    $logs = $member->getTimeLogs()->filter(function ($log) use ($shift) {
                        return ($log->getShift() && $log->getShift()->getId() == $shift->getId());
                    });
                    // Insert log if it doesn't exist fot this shift
                    if ($logs->count() == 0) {
                        $log = $this->time_log_service->initShiftValidatedTimeLog($shift, $shift->getStart(), "Créneau réalisé");
                        $this->em->persist($log);
                        $countShiftLogs++;
                    }
                }
            }
        }

        $this->em->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');

        return 0;
    }
}
