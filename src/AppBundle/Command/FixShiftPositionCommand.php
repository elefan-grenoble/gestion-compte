<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixShiftPositionCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:fix_shift_position')
            ->setDescription('Fix shifts without position (find and attach corresponding position)')
            ->setHelp('This command allows you to fix missing shift position data (most likely deleted by the migration Version20211223205749');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $shifts_without_position = $em->getRepository('AppBundle:Shift')->findBy(array('position' => null));
        $output->writeln(count($shifts_without_position) . ' créneau' . ((count($shifts_without_position)>1) ? 'x':'') . ' sans poste type trouvé' . ((count($shifts_without_position)>1) ? 's':''));

        if ($shifts_without_position) {
            $output->writeln('Premier créneau trouvé : ' . $shifts_without_position[0]->getDisplayDateFullWithTime());
            $output->writeln('Dernier créneau trouvé : ' . end($shifts_without_position)->getDisplayDateFullWithTime());

            $shifts_without_position_fixed = 0;
            // faster to loop on periodPositions
            $period_positions = $em->getRepository('AppBundle:PeriodPosition')
                ->createQueryBuilder('pp')
                ->leftJoin('pp.period', 'p')->addSelect('p')
                ->getQuery()
                ->getResult();
            $output->writeln('Boucle sur chacun des ' . count($period_positions) . ' postes types');
            foreach ($period_positions as $period_position) {
                // find shifts_without_position corresponding to this period_position
                $period_position_shifts_without_position = $em->getRepository('AppBundle:Shift')->createQueryBuilder('s')
                    // ->set('DATEFIRST', 1)
                    ->where('s.position is null')
                    ->andWhere("DATE_FORMAT(s.start, '%H:%i') = :period_start_time")
                    ->andWhere("DATE_FORMAT(s.end, '%H:%i') = :period_end_time")
                    ->andWhere("DATE_FORMAT(s.start, '%w') = :period_day_of_week")
                    // ->andWhere()  // week cycle
                    ->andWhere('s.job = :job')
                    ->setParameter('period_start_time', $period_position->getPeriod()->getStart()->format('H:i'))
                    ->setParameter('period_end_time', $period_position->getPeriod()->getEnd()->format('H:i'))
                    ->setParameter('period_day_of_week', ($period_position->getPeriod()->getDayOfWeek() == 6) ? 0 : ($period_position->getPeriod()->getDayOfWeek() + 1))
                    // ->setParameter('period_position_week_cycle', $period_position->getWeekCycle())
                    ->setParameter('job', $period_position->getPeriod()->getJob())
                    ->getQuery()
                    ->getResult();
                $shifts_without_position_fixed += count($period_position_shifts_without_position);

                // update them
                $em->createQuery("UPDATE AppBundle:Shift s SET s.position = :position WHERE s.id in (:ids)")
                    ->setParameter('position', $period_position->getId())
                    ->setParameter('ids', $period_position_shifts_without_position)
                    ->execute();
            }

            $output->writeln($shifts_without_position_fixed . ' créneau' . (($shifts_without_position_fixed>1) ? 'x':''));
        }
    }
}
