<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImportScopeliUsersCommand extends ImportUsersCommand
{

    protected $filesystem;

    protected function configure()
    {
        $this
            ->setName('scopeli:import:users')
            ->setDescription('Import users and registration from csv')
            ->setHelp('This command allows you to import user from outside as a csv file')
            ->addArgument('file', InputArgument::REQUIRED, 'Csv file source')
            ->addOption('file_payed', 'fp', InputOption::VALUE_OPTIONAL, 'Csv file source for social share')
            ->addOption('file_kazo', 'fc', InputOption::VALUE_OPTIONAL, 'Csv file source for committee')
            ->addOption('file_frozen', 'ff', InputOption::VALUE_OPTIONAL, 'Csv file source for frozen account')
            ->addOption('delimiter','d',InputOption::VALUE_OPTIONAL,'csv delimiter',',')
            ->addOption('limit','l',InputOption::VALUE_OPTIONAL,'limit')
            ->addOption('default_mapping',null,InputOption::VALUE_NONE,'')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userFile = $input->getArgument('file');
        $kazoFile = $input->getOption('file_kazo');
        $payedFile = $input->getOption('file_payed');
        $frozenFile = $input->getOption('file_frozen');
        dump('toto');

        $contentForCsv[] = [
               0 => "code-kaso",
               1 => "nom",
               2 => "prenom",
               3 => "adresse",
               4 => "adresse comp",
               5 => "code postale",
               6 => "ville",
               7 => "telephone",
               8 => "email",
               9 => "commissions",
              10 => "conjoint",
              11 => "date inscription",
              12 => "Montant",
              13 => "Mode de paiement",
              14 =>"registrar",
        ];

// ----------------- import user from csv to custom csv ------------------------
        $users = [];
        $usersName = [];
        $row = 1;
        if (($handle = fopen($userFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $input->getOption('delimiter'))) !== FALSE) {
                if (empty($data[0]) && empty($data[1]) && empty($data[2])) {
                    // empty line
                } elseif (array_key_exists($data[0],$users)) {
                    $this->WriteAndLog("$userFile line $row mail already exist ".$data[0]." ".$data[1]." ".$data[2], 'Mail', $output);
                } else if (in_array($data[8],$users)) {
                    $this->WriteAndLog("$userFile line $row matricule already exist ".$data[0]." ".$data[1]." ".$data[2], 'Matricule', $output);
                } elseif (empty($data[0])) {
                    $this->WriteAndLog("$userFile line $row matricule not set ".$data[0]." ".$data[1]." ".$data[2], 'Matricule', $output);
                } elseif (empty($data[1])) {
                    $this->WriteAndLog("$userFile line $row nom not set ".$data[0]." ".$data[1]." ".$data[2], 'Nom', $output);
                } elseif (empty($data[2])) {
                    $this->WriteAndLog("$userFile line $row prenom not set ".$data[0]." ".$data[1]." ".$data[2], 'Prenom', $output);
                } elseif (empty($data[3])) {
                    $this->WriteAndLog("$userFile line $row adresse not set ".$data[0]." ".$data[1]." ".$data[2], 'Adresse', $output);
                }  elseif (empty($data[5])) {
                    $this->WriteAndLog("$userFile line $row code postale not set ".$data[0]." ".$data[1]." ".$data[2], 'Code postale', $output);
                } elseif (empty($data[6])) {
                    $this->WriteAndLog("$userFile line $row ville not set ".$data[0]." ".$data[1]." ".$data[2], 'Ville', $output);
                } elseif (empty($data[7])) {
                    $this->WriteAndLog("$userFile line $row telephone not set ".$data[0]." ".$data[1]." ".$data[2], 'Telephone', $output);
                } elseif (empty($data[8])) {
                    $this->WriteAndLog("$userFile line $row email not set ".$data[0]." ".$data[1]." ".$data[2], 'Email', $output);
                }elseif ($row > 1) {
                    $contentForCsv[$data[0]] = [
                        0 => '',
                        1 => $data[1],
                        2 => $data[2],
                        3 => $data[3],
                        4 => $data[4],
                        5 => $data[5],
                        6 => $data[6],
                        7 => $data[7],
                        8 => $data[8],
                        9 => '',
                       10 => '',
                       11 => date('Y-m-d'),
                       12 => '',
                       13 => '',
                       14 => '',
                    ];
                    $users[$data[0]] = $data[8];
                }
                $row ++;
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>$userFile is convert to array -- $userNmber users</info>");
        } else {
            $output->writeln("<error>$userFile is not readable</error>");
        }

// ----------------- add kazo in csv -------------------------------------------
        $usersKaso = [];
        $existCodeKaso = [];
        $row = 1;
        if (($handle = fopen($kazoFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $input->getOption('delimiter'))) !== FALSE) {
                if ((empty($data[0]) || $data[0] == 0) && (empty($data[6]) || $data[6] == 0)) {
                    // empty line
                } elseif (empty($data[0])) {
                    $this->WriteAndLog("$kazoFile line $row matricule not set ".$data[0]." ".$data[1]." ".$data[2], 'Matricule', $output);
                } elseif (empty($data[6])) {
                    $this->WriteAndLog("$kazoFile line $row code kazo not set ".$data[0]." ".$data[1]." ".$data[2], 'Kaso', $output);
                    unset($contentForCsv[$data[0]]);
                } elseif (in_array($data[6],$existCodeKaso)) {
                    $this->WriteAndLog("$kazoFile line $row code kazo ever exist ".$data[0]." ".$data[1]." ".$data[2], 'Kaso', $output);
                } elseif ($row > 1 && !empty($contentForCsv[$data[0]])) {
                    $contentForCsv[$data[0]][0] = $data[6];
                    $usersKaso[] = $data[0];
                    $existCodeKaso[] = $data[6];
                }
                $row ++;
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>$payedFile : import content for kaso users -- $userNmber users</info>");
            // remove user without Montant
            foreach ($contentForCsv as $key => $value) {
                if (!in_array($key,$usersKaso) && $key != 0|| !isset($value[0])) {
                    unset($contentForCsv[$key]);
                    $this->WriteAndLog("user not in file ".$data[0]." ".$data[1]." ".$data[2], 'not in kazo', $output);
                }
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>clean for unset content  -- $userNmber users</info>");
        } else {
            $output->writeln("<error>$payedFile is not readable</error>");
        }

// ----------------- add payment in csv ----------------------------------------
        $usersPayed = [];
        $row = 1;
        if (($handle = fopen($payedFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $input->getOption('delimiter'))) !== FALSE) {
                if (
                    (empty($data[0]) || $data[0] == 0)
                    && (empty($data[4]))
                ) {
                    // empty line
                } elseif (empty($data[0])) {
                    $this->WriteAndLog("$payedFile line $row matricule not set ".$data[0]." ".$data[1]." ".$data[2], 'Matricule', $output);
                } elseif (empty($data[4])) {
                    $this->WriteAndLog("$payedFile line $row montant not set ".$data[0]." ".$data[1]." ".$data[2], 'Montant', $output);
                    unset($contentForCsv[$data[0]]);
                } elseif ($row > 1 && !empty($contentForCsv[$data[0]])) {
                    $contentForCsv[$data[0]][12] = $data[4];
                    switch ($data[5]) {
                        case "espèces" :
                            $contentForCsv[$data[0]][13] = 1;
                            break;
                        case "chèque" :
                            $contentForCsv[$data[0]][13] = 2;
                            break;
                        case "Retz'L" :
                            $contentForCsv[$data[0]][13] = 3;
                            break;
                        case "banque" :
                            $contentForCsv[$data[0]][13] = 4;
                            break;
                    }
                    $usersPayed[] = $data[0];
                }
                $row ++;
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>$payedFile : import content for payed users -- $userNmber users</info>");

            // remove user without Montant
            foreach ($contentForCsv as $key => $value) {
                if (!in_array($key,$usersPayed)) {
                    $this->WriteAndLog("user not in file ".$contentForCsv[$key][0]." ".$contentForCsv[$key][1]." ".$contentForCsv[$key][2], 'Montant', $output);
                    unset($contentForCsv[$key]);
                }
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>clean for unset content  -- $userNmber users</info>");
        } else {
            $output->writeln("<error>$payedFile is not readable</error>");
        }


        // import config
        $array_default_mapping = [
          "member_number"   =>  [
            "label" => "code-kaso",
            "index" => 0,
            "required" => false,
            "default" => null,
        ],
          "last_name"       =>  ["label" => "nom", "index" => 1],
          "first_name"      =>  ["label" => "prenom","index" => 2],
          "street1" =>  ["label" => "adresse","index" => 3,],
          "street2" =>  [
            "label" => "adresse comp",
            "required" => false,
            "default" => null,
            "index" => 4,
          ],
          "zip"             =>  ["label" => "code postale","index" => 5],
          "city"            =>  ["label" => "ville","index" => 6],
          "phone"           =>  ["label" => "telephone","index" => 7],
          "email"           =>  ["label" => "email","index" => 8],
          "commissions"     =>  [
            "label" => "commissions",
            "required" => false,
            "index" => 9,
        ],
          "add_to"          =>  [
            "label" => "conjoint",
            "index" => 10,
            "required" => false,
            "default" => "",
        ],
          "date"            =>  [
            "label" => "date inscription",
            "required" => false,
            "default" => date('Y-m-d'),
            "index" => 11,
        ],
          "amount"          =>  ["label" => "Montant","index" => 12],
          "mode"            =>  [
            "label" => "Mode de paiement",
            "required" => false,
            "default" => 5,
            "index" => 13,
        ],
          "registrar"       =>  [
            "label" => "Membre ayant réalisé l'inscription",
            "required" => false,
            "default" => 1,
            "index" => 14,
          ]
        ];
        $this->setNeededFields($array_default_mapping);
        $input->setOption('default_mapping',$this->getNeededFields());
        $this->writeTempCsv($contentForCsv);
        $input->setArgument('file','temp.csv');

        // execute import
        parent::execute($input,$output);

        $row = 1;
        $idsFrozen = [];
        if (($handle = fopen($frozenFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $input->getOption('delimiter'))) !== FALSE) {
                if ($row > 1 && isset($contentForCsv[$data[0]])) {
                    $idsFrozen[] = $contentForCsv[$data[0]][0];
                }
                $row ++;
            }
        } else {
            $output->writeln("<error>$frozenFile is not readable</error>");
        }

        $em = $this->getContainer()->get('doctrine')->getManager('default');
        $memberToFrozen = $em->getRepository('AppBundle:Membership')->findBy( array('id' => $idsFrozen));
        foreach ($memberToFrozen as $member) {
            $member->setFrozen(true);
            $em->persist($member);
        }
        $em->flush();

    }

    public function writeAndLog($message, $field = '', $output)
    {
      if(!$this->filesystem){
        $this->filesystem = new Filesystem();
      }
      $this->filesystem->appendToFile($this->getContainer()->getParameterBag()->get('kernel.logs_dir').'/error-import-user-scopeli'.date('y-m-d-h-m').'.log', date("Ymd-H:i:s").' ['.$field.'] '.$message.PHP_EOL);
      $output->writeln("<comment>[$field] $message</comment>");
    }

    public function writeTempCsv($contentForCsv)
    {
        $put = fopen('temp.csv', 'w');
        foreach($contentForCsv as $user) {
            fputcsv($put, $user);
        }
        fclose($put);
    }
}
