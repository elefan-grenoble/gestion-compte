<?php
// src/App/Command/ImportUsersCommand.php
namespace App\Command;

use App\Entity\Address;
use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\User;
use App\Event\BeneficiaryCreatedEvent;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Validator\Constraints\DateTime;

class ImportUsersCommand extends CsvCommand
{

    protected function configure()
    {
        $this
            ->setName('app:import:users')
            ->setDescription('Import users and registration from csv')
            ->setHelp('This command allows you to import user from outside as a csv file')
            ->addArgument('file', InputArgument::REQUIRED, 'Csv file source')
            ->addOption('delimiter','d',InputOption::VALUE_OPTIONAL,'csv delimiter',';')
            ->addOption('limit','l',InputOption::VALUE_OPTIONAL,'limit')
            //->addOption('dry_run',null,InputOption::VALUE_NONE,'dry run')
            ->addOption('default_mapping',null,InputOption::VALUE_NONE,'')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $delimiter= $input->getOption('delimiter');
        //$dry_run= $input->getOption('dry_run');
        $limit= $input->getOption('limit');
        $default_mapping= $input->getOption('default_mapping');

        $delimiter = $this->checkDelimiter($file,$delimiter,$input,$output);

        $output->writeln([
            '====================================',
            '    Import users data from csv      ',
            '====================================',
        ]);
        //no	nom	prénom	adresse	code postal	commune	Date de naissance	téléphone	mail	Commissions	Personne Associée	montant des parts	paiment 2 fois

        $this->setNeededFields(array(
            'member_number' => array('label' => 'Numero existant','index'=>0,"required" => false,"default" =>null),
            'last_name' => array('label' => 'Nom','index'=>1),
            'first_name' => array('label' => 'Prénom','index'=>2),
            'street1' => array('label' => 'Rue','index'=>3),
            'street2' => array('label' => 'Complement adresse',"required" => false,"default" =>null),
            'zip' => array('label' => 'Code postale','index'=>4),
            'city' => array('label' => 'Ville','index'=>5),
            'phone' => array('label' => 'Téléphone','index'=>7),
            'email' => array('label' => 'Email','index'=>8),
            'commissions' => array('label' => 'Commission (liste id)',
                'tips'=>'Liste d\'id de commissions, séparé par des virgules. ex: 1,5,7',
                "required" => false,"default" =>''),
            'add_to' => array('label' => 'Email du compte associé','index'=>10,"required" => false,"default" =>''),
            'date' => array('label' => 'Date inscription',
                'tips'=>'Au format d/m/Y ex: 13/12/2018',
                "required" => false,"default" =>date("d/m/Y")),
            'amount' => array('label' => 'Montant','index'=>11),
            'mode' => array('label' => 'Mode de paiement',
                'tips'=>'int : 1 = espèce,2 = chèque, 3 = monnaie locale, 4 = cb, 6 = Helloasso, 5 = autre',
                "required" => false,"default" =>5),
            'registrar' => array('label' => 'Membre ayant réalisé l\'inscription',
                'tips'=>'Id de l\'utilisateur. Ex: 45. Default is 1.',
                "required" => false,"default" =>1),
            //todo : manage extra fields
            //'dob' => array('label' => 'Date de naissance (d/m/Y)','index'=>6),
            //'splited_invoice' => array('label' => 'Payé en 2 fois','index'=>-1,"required" => false),
        ));

        if (!$default_mapping)
            $this->mapField($file,$delimiter,$input,$output);

        $lines = $this->getLines($file) - 1;
        if ($limit){
            $lines = min($lines,$limit);
        }
        $output->writeln("<info>Dealing with $lines lines</info>");

        $progress = new ProgressBar($output,$lines);

        $em = $this->getContainer()->get('doctrine')->getManager();

        $row = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                $row++;
                if ($limit and $limit <= $row){
                    break;
                }
                $data = array_map("utf8_encode", $data); //utf8
                if ($row > 1) { //skip first line
                    $progress->advance();

                    $output->writeln("",OutputInterface::VERBOSITY_DEBUG);

                    $member_number = ($this->getField('member_number',$data));
                    $first_name = ($this->getField('first_name',$data));
                    $last_name = ($this->getField('last_name',$data));
                    $street1 = ($this->getField('street1',$data));
                    $street2 = ($this->getField('street2',$data));
                    $zip = ($this->getField('zip',$data));
                    $city = ($this->getField('city',$data));
                    $phone = ($this->getField('phone',$data));
                    $email = ($this->getField('email',$data));
                    $commissions = explode(',',$this->getField('commissions',$data));
                    $add_to = ($this->getField('add_to',$data));
                    $date = date_create_from_format('d/m/Y',$this->getField('date',$data));
                    $amount = ($this->getField('amount',$data));
                    $mode = ($this->getField('mode',$data));
                    $registrar = ($this->getField('registrar',$data));

                    $membership_is_new = false;
                    $user_is_new = false;

                    $membership = new Membership();
                    if ($add_to){
                        $parent_user = $em->getRepository(User::class)->findOneBy(array('email'=>$add_to));
                        if ($parent_user && $parent_user->getId()){
                            $membership = $parent_user->getBeneficiary()->getMembership();
                            $output->writeln("<info>Membership found for parent email <fg=cyan>$add_to</> (#<fg=cyan>".$membership->getMemberNumber()."</>), using it</info>",OutputInterface::VERBOSITY_DEBUG);
                        }
                    }
                    if (!$membership->getId()){
                        $membership = $em->getRepository(Membership::class)->findOneBy(array('member_number'=>$member_number));
                        if ($membership && $membership->getId()){
                            $output->writeln("<info>Membership with number <fg=cyan>$member_number</> exist, using it</info>",OutputInterface::VERBOSITY_DEBUG);
                        }else{
                            $membership_is_new = true;
                            $membership = new Membership();
                            $output->writeln("<info>No Membership with number <fg=cyan>$member_number</> found, create one</info>",OutputInterface::VERBOSITY_DEBUG);
                            $membership->setMemberNumber($member_number);
                            $membership->setWithdrawn(false);
                            $membership->setFrozen(false);
                            $membership->setFrozenChange(false);
                            $em->persist($membership);
                        }
                    }

                    $user = $em->getRepository(User::class)->findOneBy(array('email'=>$email));
                    $beneficiary = new Beneficiary();

                    if ($user && $user->getId()){
                        $output->writeln("<info>User with email <fg=cyan>$email</> found. Update it</info>",OutputInterface::VERBOSITY_DEBUG);
                        $beneficiary = $user->getBeneficiary();
                    }else{
                        $user_is_new = true;
                        $output->writeln("<info>Create new User and Beneficiary (<fg=cyan>$last_name $first_name $email</>)</info>",OutputInterface::VERBOSITY_DEBUG);

                        $beneficiary->setFirstname($first_name);
                        $beneficiary->setLastname($last_name);
                        $beneficiary->setPhone($phone);

                        $dispatcher = $this->getContainer()->get('event_dispatcher');
                        $dispatcher->dispatch(BeneficiaryCreatedEvent::NAME, new BeneficiaryCreatedEvent($beneficiary));

                        $beneficiary->setEmail($email);

                        $em->persist($beneficiary);
                    }

                    $address = new Address();
                    if ($beneficiary->getAddress()){
                        $address = $beneficiary->getAddress();
                    }

                    $address->setCity($city);
                    $address->setStreet1($street1);
                    $address->setStreet2($street2);
                    $address->setZipcode($zip);
                    $em->persist($address);

                    $beneficiary->setAddress($address);
                    $beneficiary->setMembership($membership);
                    if ($membership_is_new){
                        $membership->setMainBeneficiary($beneficiary);
                        $em->persist($membership);
                    }
                    $em->persist($beneficiary);

                    $registration = new Registration();
                    if (!$user_is_new){ //do not add registration
                        foreach ($membership->getRegistrations() as $r){
                            if ($r->getDate()->format('Y')==$date->format('Y')){
                                $output->writeln("<info>Registration within this year (<fg=cyan>".$date->format('Y')."</>) found. Do nothing</info>",OutputInterface::VERBOSITY_DEBUG);
                                $registration = null;
                                break;
                            }
                        }
                    }
                    if ($registration){
                        $registration->setDate($date);
                        $registration->setMembership($membership);
                        $registration->setAmount($amount);
                        $registrar = $em->getRepository(User::class)->findOneBy(array('id'=>$registrar));
                        $registration->setRegistrar($registrar);
                        $registration->setMode($mode);
                        $em->persist($registration);
                    }

                    foreach ($commissions as $commission_id){
                        if ($commission_id){
                            $commission =  $em->getRepository(Commission::class)->findOneBy(array('id'=>$commission_id));
                            if ($commission){
                                $beneficiary->addCommission($commission);
                            }else{
                                $output->writeln("<error>Commission with id  #<fg=cyan>".$commission_id."</> not found</error>",OutputInterface::VERBOSITY_DEBUG);
                            }
                        }
                    }
                    $em->persist($beneficiary);

                    $em->flush();

                }
            }
            fclose($handle);
            $output->writeln("");
        }
        //$progress->finish();
    }

}