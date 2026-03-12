<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\ORMException;

class InitShiftFreeLogShiftStringFieldCommand extends Command
{
    private $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:shiftfreelog:init_shift_string_field')
            ->setDescription('Init ShiftFreeLog.shiftString data')
            ->setHelp('This command allows you to init ShiftFreeLog.shiftString data')
        ;
    }

    /**
     * @throws ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $countShiftFreeLogs = 0;
        $shiftFreeLogs = $this->em->getRepository('App:ShiftFreeLog')->findAll();

        foreach ($shiftFreeLogs as $shiftFreeLog) {
            if (!$shiftFreeLog->getShiftString()) {
                if ($shiftFreeLog->getShift()) {
                    $shiftString = $shiftFreeLog->getShift()->getJob()->getName() . ' - ' . $shiftFreeLog->getShift()->getDisplayDateSeperateTime();
                    $shiftFreeLog->setShiftString($shiftString);
                    $this->em->persist($shiftFreeLog);
                    ++$countShiftFreeLogs;
                }
            }
        }

        $this->em->flush();
        $output->writeln($countShiftFreeLogs . ' logs mis à jour !');

        return 0;
    }
}
