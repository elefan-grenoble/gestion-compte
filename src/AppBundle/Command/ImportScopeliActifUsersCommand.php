<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use FOS\UserBundle\Model\UserManagerInterface;

class ImportScopeliActifUsersCommand extends ImportUsersCommand
{

    protected $filesystem;

    private $userManager;

    public function __construct(UserManagerInterface $userManager, $name = null)
    {
        $this->userManager = $userManager;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('scopeli:import:actif-users')
            ->setDescription('Import users and registration from csv')
            ->setHelp('This command allows you to import user from outside as a csv file')
            ->addArgument('file', InputArgument::REQUIRED, 'Csv file source')
            ->addOption('delimiter','d',InputOption::VALUE_OPTIONAL,'csv delimiter',',')
            ->addOption('limit','l',InputOption::VALUE_OPTIONAL,'limit')
            ->addOption('default_mapping',null,InputOption::VALUE_NONE,'')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userFile = $input->getArgument('file');

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
              14 => "registrar",
        ];

// ----------------- import user from csv to custom csv ------------------------
        $users = [];
        $usersName = [];
        $row = 1;

        $em = $this->getContainer()->get('doctrine')->getManager();

        if (($handle = fopen($userFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $input->getOption('delimiter'))) !== FALSE) {
                $data[1] = str_replace(' ', '',$data[1]);
                if (empty($data[1]) && empty($data[5])) {
                    // empty line
                } elseif (array_key_exists($data[1],$users)) {
                    $this->WriteAndLog("$userFile line $row code kazo already exist ".$data[1]." ".$data[2]." ".$data[3], 'Kazo', $output);
                } else if (in_array($data[5],$users)) {
                    $this->WriteAndLog("$userFile line $row email already exist ".$data[1]." ".$data[2]." ".$data[3], 'Email', $output);
                } else if (!$em->getRepository('AppBundle:Membership')->findBy(['member_number' => $data[1]])) {
                    $this->WriteAndLog("$userFile line $row code kazo ever import ".$data[1]." ".$data[2]." ".$data[3], 'Kazo', $output);
                } else if (!$this->userManager->findUserByEmail($data[5])) {
                    $this->WriteAndLog("$userFile line $row user email ever exist ".$data[1]." ".$data[2]." ".$data[3], 'Email', $output);
                } elseif (empty($data[1])) {
                    $this->WriteAndLog("$userFile line $row code kazo not set ".$data[1]." ".$data[2]." ".$data[3], 'Kazo', $output);
                } elseif (!intval($data[1])) {
                    $this->WriteAndLog("$userFile line $row code kazo not integer ".$data[1]." ".$data[2]." ".$data[3], 'Kazo', $output);
                } elseif (empty($data[2])) {
                    $this->WriteAndLog("$userFile line $row nom not set ".$data[1]." ".$data[2]." ".$data[3], 'Nom', $output);
                } elseif (empty($data[3])) {
                    $this->WriteAndLog("$userFile line $row prenom not set ".$data[1]." ".$data[2]." ".$data[3], 'Prenom', $output);
                } elseif (empty($data[4])) {
                    $this->WriteAndLog("$userFile line $row telephone not set ".$data[1]." ".$data[2]." ".$data[3], 'Telephone', $output);
                } elseif (empty($data[5])) {
                    $this->WriteAndLog("$userFile line $row email not set ".$data[1]." ".$data[2]." ".$data[3], 'Email', $output);
                }elseif ($row > 1) {
                    $contentForCsv[$data[1]] = [
                        0 => $data[1],
                        1 => $data[2],
                        2 => $data[3],
                        3 => NULL,
                        4 => NULL,
                        5 => NULL,
                        6 => NULL,
                        7 => $data[4],
                        8 => trim($data[5]),
                        9 => NULL,
                       10 => NULL,
                       11 => date('Y-m-d'),
                       12 => 50,
                       13 => NULL,
                       14 => NULL,
                    ];
                    $users[$data[1]] = trim($data[5]);
                }
                $row ++;
            }
            $userNmber = \count($contentForCsv);
            $output->writeln("<info>$userFile is convert to array -- $userNmber users</info>");
        } else {
            $output->writeln("<error>$userFile is not readable</error>");
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

        // active user
        foreach ($contentForCsv as $importUser) {
            $user = $this->userManager->findUserByEmail($importUser[8]);
            if ($user){
                $user->setEnabled(true);
                $this->userManager->updateUser($user);
            } else {
                    $this->WriteAndLog("error with ".$data[1]." ".$data[2]." ".$data[3]." ".$data[8],'active user', $output);
            }
        }
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
