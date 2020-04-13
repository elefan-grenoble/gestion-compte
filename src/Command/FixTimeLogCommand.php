<?php
// src/App/Command/FixTimeLogCommand.php
namespace App\Command;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\TimeLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixTimeLogCommand extends Command
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
            ->setName('app:user:fix_time_log')
            ->setDescription('Fix time logs data')
            ->setHelp('This command allows you to fix time logs data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $countShiftLogs = 0;
        $members = $this->entityManager->getRepository('App:Membership')->findAll();
        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {

                $lastCycleShifts = $member->getShiftsOfCycle(-1, true)->toArray();
                $currentCycleShifts = $member->getShiftsOfCycle(0, true)->toArray();
                $shifts = array_merge($lastCycleShifts, $currentCycleShifts);
                foreach ($shifts as $shift) {

                    $logs = $member->getTimeLogs()->filter(function ($log) use ($shift) {
                        return ($log->getShift() && $log->getShift()->getId() == $shift->getId());
                    });

                    // Insert log if it doesn't exist fot this shift
                    if ($logs->count() == 0) {
                        $this->createShiftLog($shift, $member);
                        $countShiftLogs++;
                    }
                }
            }
        }
        $this->entityManager->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
    }

    private function createShiftLog(Shift $shift, Membership $membership)
    {
        $log = new TimeLog();
        $log->setMembership($membership);
        $log->setTime($shift->getDuration());
        $log->setShift($shift);
        $log->setDate($shift->getStart());
        $log->setDescription("Créneau réalisé");
        $this->entityManager->persist($log);
    }

}