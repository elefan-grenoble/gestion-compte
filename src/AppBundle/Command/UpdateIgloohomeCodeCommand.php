<?php

namespace AppBundle\Command;

use AppBundle\Entity\Code;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class UpdateIgloohomeCodeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:code:update_igloohome')
            ->setDescription('Update igloohome code')
            ->setHelp('This command create an igloohome code using the API and set it the code table')
            ->addArgument('codedevice', InputArgument::REQUIRED, 'Codedevice number')
            ->addArgument('start', InputArgument::REQUIRED, 'Start of code validity (ISO 8601 format)')
            ->addArgument('end', InputArgument::REQUIRED, 'End of code validity (ISO 8601 format)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = $input->getArgument('start');
        $end = $input->getArgument('end');
        $cd = $input->getArgument('codedevice');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $codeDevice = $em->getRepository('AppBundle:Code')->findOneBy(array('id' => $cd));

        $newCodeValue = $this->getContainer()->get('code_service')->generateIgloohomeCode($codeDevice, $start, $end, $start);
        if($newCodeValue== null) {
            $output->writeln('<fg=cyan;>>>></><fg=red;> Impossible de générer le code </>');
            return;
        }
        $output->writeln('<fg=cyan;>>>></><fg=green;> Code créé avec succès via l\'API Igloohome : ' . $newCodeValue . '</>');

        // Get the admin user
        $adminUsername = $this->getContainer()->getParameter('super_admin.username');
        $userRepository = $em->getRepository('AppBundle:User');
        $adminUser = $userRepository->findOneByUsername($adminUsername);

        // Insert the new code created from the Igloohome API
        $code = new Code();
        $code->setValue($newCodeValue);
        $code->setClosed(false);
        $code->setRegistrar($adminUser);

        $em->persist($code);

        $em->flush();

        $output->writeln('<fg=cyan;>>>></><fg=green;> Nouveau code généré avec succès </>');
    }

}
