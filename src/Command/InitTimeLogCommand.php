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

class InitTimeLogCommand extends Command
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
            ->setName('app:user:init_time_log')
            ->setDescription('Init time logs data')
            ->setHelp('This command allows you to init time logs data');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $countShiftLogs = 0;
        $countCycleBeginning = 0;
        $members = $this->em->getRepository('App:Membership')->findAll();
        $beginningOfLastCycle = new \DateTime('28 days ago');
        $beginningOfLastCycle->setTime(0, 0, 0);

        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {
                $previous_cycle_start = $this->membership_service->getStartOfCycle($member, -1);
                $current_cycle_end = $this->membership_service->getEndOfCycle($member, 0);
                $shifts = $this->em->getRepository('App:Shift')->findShiftsForMembership($member, $previous_cycle_start, $current_cycle_end);
                foreach ($shifts as $shift) {
                    $log = $this->time_log_service->initShiftValidatedTimeLog($shift, $shift->getStart());
                    $this->em->persist($log);
                    $countShiftLogs++;
                }

                if ($member->getFirstShiftDate() < $beginningOfLastCycle) {
                    $log = $this->time_log_service->initCurrentCycleBeginningTimeLog($member);
                    $this->em->persist($log);
                    $countCycleBeginning++;
                }
            }
        }

        $this->em->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
        $output->writeln($countCycleBeginning . ' logs de début de cycle créés');

        return 0;
    }
}
