<?php

namespace AppBundle\Command;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixTimeLogSavingCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:fix_time_log_saving')
            ->setDescription('Fix time logs saving data')
            ->setHelp('This command allows you to fix time logs saving data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $countSavingLogs = 0;
        $due_duration_by_cycle = $this->getContainer()->getParameter('due_duration_by_cycle');
        $use_time_log_saving = $this->getContainer()->getParameter('use_time_log_saving');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $members = $em->getRepository('AppBundle:Membership')->findAll();

        if ($use_time_log_saving) {
            $output->writeln('Parameter use_time_log_saving is true');
            $output->writeln('Parameter due_duration_by_cycle = '. $due_duration_by_cycle);
            foreach ($members as $member) {
                if (!$member->isWithdrawn() && $member->getFirstShiftDate()) {
                    $member_counter_now = $member->getShiftTimeCount();
                    $extra_counter_time = $member_counter_now - $due_duration_by_cycle;

                    // the extra time will go in the member's saving account
                    // same logic as TimeLogEventListener > createShiftValidatedTimeLog
                    if ($extra_counter_time > 0) {
                        $output->writeln('=====');
                        $output->writeln('Member ' . $member);
                        $output->writeln('counter_now before ' . $member_counter_now);
                        $output->writeln('extra_counter_time before = ' . $extra_counter_time);

                        // get member last timelog
                        $log = $member->getTimeLogs()->first();
                        $output->writeln('dernier timelog ' . $log);
                        $output->writeln('créneau du dernier timelog ' . $log->getShift());

                        // // first decrement the shiftTimeCount
                        // $log = $this->getContainer()->get('time_log_service')->initRegulateOptionalShiftsTimeLog($member, -1 * $extra_counter_time);
                        // $this->em->persist($log);
                        // // then increment the savingTimeCount
                        // $log = $this->getContainer()->get('time_log_service')->initSavingTimeLog($member, $extra_counter_time, $shift);
                        // $this->em->persist($log);
                        // $this->em->flush();

                        $member_counter_now = $member->getShiftTimeCount();
                        $extra_counter_time = $member_counter_now - $due_duration_by_cycle;

                        $output->writeln('counter_now after ' . $member_counter_now);
                        $output->writeln('extra_counter_time after = ' . $extra_counter_time);
                    }
                }
            }

            // $em->flush();
            // $output->writeln($countShiftLogs . ' logs de créneaux réalisés créés');
        } else {
            $output->writeln('Parameter use_time_log_saving must be true');
        }
    }
}
