<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Beneficiary;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RandomSortMembersCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var string
     */
    private $registrationDuration;

    public function __construct(EntityManagerInterface $entityManager, string $registrationDuration)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->registrationDuration = $registrationDuration;
    }

    protected function configure()
    {
        $this
            ->setName('app:beneficiary:randomise')
            ->setDescription('Get a random list of beneficiary up on uptodate membership')
            ->setHelp('This command give you a file containing a list of up to date beneficiary sorted randomly')
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


        $qb = $this->entityManager->getRepository(Beneficiary::class)->createQueryBuilder('b');
        $qb = $qb->leftJoin("b.membership", "m")->addSelect("m");
        $qb = $qb->leftJoin("m.registrations", "r")->addSelect("r"); //registrations
        $qb = $qb->leftJoin("m.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL'); //registration is the last one registered
        $qb = $qb->andWhere('m.withdrawn = 0'); //do not include withdrawn
        if ($exclude_frozen){
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> ne pas inclure les comptes gelés </>');
            $qb = $qb->andWhere('m.frozen != 1');
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> les comptes gelés sont inclus </>');
        }

        $last_registration->modify("-" . $this->registrationDuration);

        $output->writeln('<fg=cyan;>>>></><fg=green;> membres avec dernière (re)adhésion après le </><fg=yellow;>'.$last_registration->format('D d M Y').' </>');
        $qb = $qb->andWhere('r.date > :lastregistrationdategt')->setParameter('lastregistrationdategt', $last_registration);

        if ($given_mdate){
            $output->writeln('<fg=cyan;>>>></><fg=green;> et membres avec dernière (re)adhésion avant le </><fg=yellow;>'.$max_last_registration->format('D d M Y').' </>');
            $qb = $qb->andWhere('r.date <= :lastregistrationdatelt')->setParameter('lastregistrationdatelt', $max_last_registration);
        }

        $beneficiaries = $qb->getQuery()->getResult();

        $output->writeln('<fg=cyan;>>>> <fg=yellow;>'.count($beneficiaries).'</><fg=green;> beneficiaires à jour </>');

        shuffle($beneficiaries);
        $csv = 'Index, Numéro de membre, Prénom, Nom, Téléphone, Email'."\n";
        $index = 1;
        /** @var Beneficiary $beneficiary */
        foreach ($beneficiaries as $beneficiary){
            $csv .= $index++.',';
            $csv .= $beneficiary->getMembership()->getMemberNumber().',';
            $csv .= $beneficiary->getFirstName().',';
            $csv .= $beneficiary->getLastName().',';
            $csv .= intval($beneficiary->getPhone()).',';
            $csv .= $beneficiary->getEmail().',';
            $csv .=  "\n";
        }
        if ($file){
            file_put_contents($file,$csv);
        }else{
            echo $csv;
        }

    }
}