<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;

class DoctorCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:doc')
            ->setDescription('Fix db data')
            ->setHelp('This command may fix some data in DB')
            ->addOption('phone','p', InputOption::VALUE_NONE, 'Fix phones numbers')
            ->addOption('status','s', InputOption::VALUE_NONE, 'Fix status ')
            ->addOption('registration','r', InputOption::VALUE_NONE, 'Fix registrations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $fix_phone = $input->getOption('phone');
        $fix_status = $input->getOption('status');
        $fix_registration = $input->getOption('registration');

        if ($fix_phone){
            $counter = 0;
            $debug = array();
            $em = $this->getContainer()->get('doctrine')->getManager();
            $members = $em->getRepository('App:Membership')->findAll();
            foreach ($members as $member){
                foreach ($member->getBeneficiaries() as $beneficiary){
                    $phone = $beneficiary->getPhone();
                    if ($phone){
                        //space ?
                        $phone = str_replace(' ','',$phone);
                        //dot ?
                        $phone = str_replace('.','',$phone);
                        //comma ?
                        $phone = str_replace(',','',$phone);
                        //anti slash
                        $phone = str_replace('\\','',$phone);
                        //slash
                        $phone = str_replace('/','',$phone);
                        // 0 missing at start ?
                        $re = '/^[123456789][0-9]{8}$/';
                        preg_match_all($re, $phone, $matches, PREG_SET_ORDER, 0);
                        if(count($matches) >= 1){
                            $phone = '0'.$phone;
                        }
                        // to many 0 at start ?
                        $re = '/^[0][0]([0-9]*)$/';
                        preg_match_all($re, $phone, $matches, PREG_SET_ORDER, 0);
                        if(count($matches) >= 1){
                            $phone = '0'.$matches[0][1];
                        }
                        // only 0 ?
                        $re = '/^[0][0]*$/';
                        preg_match_all($re, $phone, $matches, PREG_SET_ORDER, 0);
                        if(count($matches) >= 1){
                            $phone = '';
                        }
                        //
                    }
                    if (!($phone === $beneficiary->getPhone())){ //correction exist
                        $debug[] = $beneficiary->getPhone().' devient "'.$phone.'"';
                        if(!$phone) {
                            $phone = null;
                        }
                        $beneficiary->setPhone($phone);
                        $em->persist($beneficiary);
                        $counter++;
                    }
                }
            }
            $em->flush();
            $output->writeln('<fg=cyan;>>>></><fg=green;> PHONES FIX </>');
            if ($input->getOption('verbose')){
                foreach ($debug as $d){
                    $output->writeln('<fg=magenta;>>>></> <fg=yellow;>'.$d.' </>');
                }
            }
            $output->writeln('<fg=cyan;>>>></><fg=green;> '.$counter.' numéro(s) corrigé(s)'.' </>');
        }

        if ($fix_status) {
            $counter = 0;
            $em = $this->getContainer()->get('doctrine')->getManager();
            $members = $em->getRepository('App:Membership')->findAll();
            foreach ($members as $member) {
                if (($member->getFrozen() === null)||($member->getWithdrawn() === null)){
                    if ($member->getFrozen() === null)
                        $member->setFrozen(false);
                    if ($member->getWithdrawn() === null)
                        $member->setWithdrawn(false);
                    $em->persist($member);
                    $counter++;
                }
            }
            $em->flush();
            $output->writeln('<fg=cyan;>>>></><fg=green;> STATUS FIX </>');
            $output->writeln('<fg=cyan;>>>></><fg=green;>'.$counter.' status vide(s) corrigé(s)'.' </>');
        }

        if ($fix_registration) {
            $counter = 0;
            $em = $this->getContainer()->get('doctrine')->getManager();
            $members = $em->getRepository('App:Membership')->findAll();
            foreach ($members as $member) {
                foreach ($member->getRegistrations() as $registration) {
                    if ($registration->getCreatedAt()->format('Y') < 0) {
                        $registration->setCreatedAt($registration->getDate());
                        $counter++;
                        $em->persist($registration);
                    }
                }
            }
            $em->flush();
            $output->writeln('<fg=cyan;>>>></><fg=green;> REGISTRATION FIX </>');
            $output->writeln('<fg=cyan;>>>></><fg=green;>'.$counter.' correction(s) apportée(s) aux adhésion(s)'.' </>');
        }

    }
}