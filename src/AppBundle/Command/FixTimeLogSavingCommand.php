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
                if ($member->getFirstShiftDate()) {
                    $counter_now = $member->getShiftTimeCount();
                    $extra_counter_time = $counter_now - $due_duration_by_cycle;

                    if ($extra_counter_time > 0) {
                        $output->writeln('=====');
                        $output->writeln('Member ' . $member);
                        $output->writeln('extra_counter_time = ' . $extra_counter_time);
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
