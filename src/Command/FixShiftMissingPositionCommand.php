<?php

namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixShiftMissingPositionCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:fix_missing_position')
            ->setDescription('Fix shifts without position (find and attach corresponding position)')
            ->setHelp('This command allows you to fix missing shift position data (most likely deleted by the migration Version20211223205749')
            ->addOption('dry_run', null, InputOption::VALUE_NONE, 'Dry run');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $cycle_type = $this->getContainer()->getParameter('cycle_type');
        $dry_run = $input->getOption('dry_run');

        if ($dry_run) {
            $output->writeln("<comment>Dry run: won't impact the database</comment>");
        }

        $shifts_without_position = $em->getRepository('App:Shift')->findBy(array('position' => null), array('start' => 'ASC'));
        $output->writeln(count($shifts_without_position) . ' créneau' . ((count($shifts_without_position)>1) ? 'x':'') . ' sans poste type trouvé' . ((count($shifts_without_position)>1) ? 's':'') . '...');

        if ($cycle_type == 'abcd') {
            // TODO : add filter on weekCycle
            // what if the cycle changed ?
            $output->writeln('<error>Currently only works for coops without cycle_type.</error>');
            return 1;
        }

        if ($shifts_without_position) {
            $output->writeln('Premier créneau trouvé : ' . $shifts_without_position[0]->getDisplayDateFullWithTime());
            $output->writeln('Dernier créneau trouvé : ' . end($shifts_without_position)->getDisplayDateFullWithTime());

            $shifts_without_position_fixed = 0;
            // faster to loop on periodPositions
            $period_positions = $em->getRepository('App:PeriodPosition')
                ->createQueryBuilder('pp')
                ->leftJoin('pp.period', 'p')->addSelect('p')
                ->getQuery()
                ->getResult();
            $output->writeln('Boucle sur chacun des ' . count($period_positions) . ' postes types');

            foreach ($period_positions as $period_position) {
                // find shifts_without_position corresponding to this period_position
                $period_position_shifts_without_position = $em->getRepository('App:Shift')->createQueryBuilder('s')
                    // ->set('DATEFIRST', 1)
                    ->where('s.position is null')
                    ->andWhere("DATE_FORMAT(s.start, '%H:%i') = :period_start_time")
                    ->andWhere("DATE_FORMAT(s.end, '%H:%i') = :period_end_time")
                    ->andWhere("DATE_FORMAT(s.start, '%w') = :period_day_of_week")
                    // ->andWhere()  // week cycle
                    ->andWhere('s.job = :job')
                    ->andWhere('s.formation = :formation')
                    ->setParameter('period_start_time', $period_position->getPeriod()->getStart()->format('H:i'))
                    ->setParameter('period_end_time', $period_position->getPeriod()->getEnd()->format('H:i'))
                    ->setParameter('period_day_of_week', ($period_position->getPeriod()->getDayOfWeek() == 6) ? 0 : ($period_position->getPeriod()->getDayOfWeek() + 1))
                    // ->setParameter('period_position_week_cycle', $period_position->getWeekCycle())
                    ->setParameter('job', $period_position->getPeriod()->getJob())
                    ->setParameter('formation', $period_position->getFormation())
                    ->orderBy('s.start')
                    ->getQuery()
                    ->getResult();

                if (count($period_position_shifts_without_position)) {
                    $output->writeln('Poste ' . $period_position->getId());
                    $output->writeln('Premier créneau trouvé : ' . $period_position_shifts_without_position[0]->getDisplayDateFullWithTime() . ' |Dernier créneau trouvé : ' . end($period_position_shifts_without_position)->getDisplayDateFullWithTime());
                    $output->writeln('Nombre de créneau correspondants : ' . count($period_position_shifts_without_position));
                    // we only want 1 shift per week
                    // why ? period can have identical positions, which generate identical shifts...
                    $period_position_shifts_without_position_unique_days = array();
                    $period_position_shifts_without_position_filtered = array();
                    foreach($period_position_shifts_without_position as $s) {
                        if (!in_array($s->getStart()->format('Y-m-d'), $period_position_shifts_without_position_unique_days)) {
                            array_push($period_position_shifts_without_position_unique_days, $s->getStart()->format('Y-m-d'));
                            array_push($period_position_shifts_without_position_filtered, $s);
                        }
                    }

                    if (!$dry_run) {
                        $em->createQuery("UPDATE App:Shift s SET s.position = :position WHERE s.id in (:ids)")
                            ->setParameter('position', $period_position)
                            ->setParameter('ids', $period_position_shifts_without_position_filtered)
                            ->execute();
                    }
                    $shifts_without_position_fixed += count($period_position_shifts_without_position_filtered);
                }
            }

            if (!$dry_run) {
                $output->writeln('<info>' . $shifts_without_position_fixed . ' créneau' . (($shifts_without_position_fixed>1) ? 'x':'') . ' réparé' . (($shifts_without_position_fixed>1) ? 's':'') . '</info>');
            }
        }

        return 0;
    }
}
