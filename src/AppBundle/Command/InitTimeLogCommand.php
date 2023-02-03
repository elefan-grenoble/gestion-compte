<?php

namespace AppBundle\Command;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitTimeLogCommand extends ContainerAwareCommand
{
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
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $countShiftLogs = 0;
        $countCycleBeginning = 0;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $members = $em->getRepository('AppBundle:Membership')->findAll();
        $beginningOfLastCycle = new \DateTime('28 days ago');
        $beginningOfLastCycle->setTime(0, 0, 0);

        foreach ($members as $member) {
            if ($member->getFirstShiftDate()) {
                $previous_cycle_start = $this->getContainer()->get('membership_service')->getStartOfCycle($member, -1);
                $current_cycle_end = $this->getContainer()->get('membership_service')->getEndOfCycle($member, 0);
                $shifts = $em->getRepository('AppBundle:Shift')->findShiftsForMembership($member, $previous_cycle_start, $current_cycle_end);
                foreach ($shifts as $shift) {
                    $log = $this->getContainer()->get('time_log_service')->initShiftValidatedTimeLog($shift, $shift->getStart());
                    $em->persist($log);
                    $countShiftLogs++;
                }

                if ($member->getFirstShiftDate() < $beginningOfLastCycle) {
                    $log = $this->getContainer()->get('time_log_service')->initCurrentCycleBeginningTimeLog($member);
                    $em->persist($log);
                    $countCycleBeginning++;
                }
            }
        }

        $em->flush();
        $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
        $output->writeln($countCycleBeginning . ' logs de début de cycle créés');
    }
}
