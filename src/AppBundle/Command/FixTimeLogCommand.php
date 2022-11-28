<?php
// src/AppBundle/Command/FixTimeLogCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
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
        $countShiftLogs = 0;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $members = $em->getRepository('AppBundle:Membership')->findAll();
        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {

                $previous_cycle_start = $this->get('membership_service')->getStartOfCycle($member, -1);
                $current_cycle_end = $this->get('membership_service')->getEndOfCycle($member, 0);
                $shifts = $em->getRepository('AppBundle:Shift')->findShiftsForMembership($member, $previous_cycle_start, $current_cycle_end, true);
                foreach ($shifts as $shift) {

                    $logs = $member->getTimeLogs()->filter(function ($log) use ($shift) {
                        return ($log->getShift() && $log->getShift()->getId() == $shift->getId());
                    });

                    // Insert log if it doesn't exist fot this shift
                    if ($logs->count() == 0) {
                        $this->createShiftLog($em, $shift, $member);
                        $countShiftLogs++;
                    }
                }
            }
        }
        $em->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
    }

    private function createShiftLog(EntityManager $em, Shift $shift, Membership $membership)
    {
        $log = new TimeLog();
        $log->setMembership($membership);
        $log->setTime($shift->getDuration());
        $log->setShift($shift);
        $log->setCreatedAt($shift->getStart());
        $log->setType(1);
        $log->setDescription("Créneau réalisé");
        $em->persist($log);
    }

}
