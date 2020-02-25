<?php
// src/AppBundle/Command/ShiftGenerateCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;

class RandomSortMembersCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:randomise')
            ->setDescription('Get a random list of up to date members')
            ->setHelp('This command give you a file containing a list of up to date members sorted randomly')
            ->addArgument('date', InputArgument::REQUIRED, 'The date for last registration to be valid (event date) (format yyyy-mm-dd)')
            ->addOption('max_date','m', InputOption::VALUE_OPTIONAL, 'The maximum date for last registration (format yyyy-mm-dd)')
            ->addOption('exclude_frozen',null, InputOption::VALUE_NONE, 'Exclude frozen accounts')
            ->addOption('file','f', InputOption::VALUE_OPTIONAL, 'file to put csv content')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $given_date = $input->getArgument('date');
        $last_registration = date_create_from_format('Y-m-d',$given_date);
        if (!$last_registration || $last_registration->format('Y-m-d') != $given_date){
            $output->writeln('<fg=red;> wrong date format for minimum date. Use Y-m-d </>');
            return;
        }
        $given_mdate = $input->getOption('max_date');
        if ($given_mdate){
            $max_last_registration = date_create_from_format('Y-m-d',$given_mdate);
            if (!$max_last_registration || $max_last_registration->format('Y-m-d') != $given_mdate){
                $output->writeln('<fg=red;> wrong date format for maximum date. Use Y-m-d </>');
                return;
            }
        }
        $file = $input->getOption('file');
        $exclude_frozen = $input->getOption('exclude_frozen');


        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->getRepository("AppBundle:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.registration", "lr")->addSelect("lr");
        $qb = $qb->andWhere('o.withdrawn = 0'); //do not include withdrawn
        if ($exclude_frozen){
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> ne pas inclure les comptes gelés </>');
            $qb = $qb->andWhere('o.frozen != 1');
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> les comptes gelés sont inclus </>');
        }

        $last_registration->modify("-".$this->getContainer()->getParameter('registration_duration'));

        $output->writeln('<fg=cyan;>>>></><fg=green;> membres avec dernière (re)adhésion après le </><fg=yellow;>'.$last_registration->format('D d M Y').' </>');
        $qb = $qb->andWhere('lr.date > :lastregistrationdategt')->setParameter('lastregistrationdategt', $last_registration);

        if ($given_mdate){
            $output->writeln('<fg=cyan;>>>></><fg=green;> et membres avec dernière (re)adhésion avant le </><fg=yellow;>'.$max_last_registration->format('D d M Y').' </>');
            $qb = $qb->andWhere('lr.date <= :lastregistrationdatelt')->setParameter('lastregistrationdatelt', $max_last_registration);
        }

        $memberships = $qb->getQuery()->getResult();

        $output->writeln('<fg=cyan;>>>></><fg=green;> '.count($memberships).' comptes membres </>');

        shuffle($memberships);
        $csv = 'Index, Numéro de membre, Prénom, Nom, Téléphone, Email, Index bénéficiaire'."\n";
        $index = 1;
        foreach ($memberships as $membership){
            $beneficiaries = $membership->getBeneficiaries()->toArray();
            $rand = rand(0,count($beneficiaries)-1);
            if ($rand >= 0 && isset($beneficiaries[$rand])){
                $beneficiary = $beneficiaries[$rand];
                $csv .= $index++.',';
                $csv .= $membership->getMemberNumber().',';
                $csv .= $beneficiary->getFirstName().',';
                $csv .= $beneficiary->getLastName().',';
                $csv .= intval($beneficiary->getPhone()).',';
                $csv .= $beneficiary->getEmail().',';
                $csv .= 'bénéficiaire #'.($rand+1);
            }else{
                $csv .= $membership->getMemberNumber().',';
            }
            $csv .=  "\n";
        }
        if ($file){
            file_put_contents($file,$csv);
        }else{
            echo $csv;
        }

    }
}