<?php

namespace AppBundle\Command;

use AppBundle\Entity\ShiftFreeLog;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitShiftFreeLogShiftStringFieldCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shiftfreelog:init_shift_string_field')
            ->setDescription('Init ShiftFreeLog.shiftString data')
            ->setHelp('This command allows you to init ShiftFreeLog.shiftString data');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $countShiftFreeLogs = 0;
        $shiftFreeLogs = $em->getRepository('AppBundle:ShiftFreeLog')->findAll();

        foreach ($shiftFreeLogs as $shiftFreeLog) {
            if (!$shiftFreeLog->getShiftString()) {
                if ($shiftFreeLog->getShift()) {
                    $shiftString = $shiftFreeLog->getShift()->getJob()->getName() . ' - ' . $shiftFreeLog->getShift()->getDisplayDateSeperateTime();
                    $shiftFreeLog->setShiftString($shiftString);
                    $em->persist($shiftFreeLog);
                    $countShiftFreeLogs++;
                }
            }
        }

        $em->flush();
        $output->writeln($countShiftFreeLogs . ' logs mis à jour !');
    }
}
