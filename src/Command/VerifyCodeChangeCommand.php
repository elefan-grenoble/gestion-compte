<?php
// src/App/Command/VerifyCodeChangeCommand.php
namespace App\Command;

use App\Entity\Shift;
use App\Security\CodeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Templating\EngineInterface;

class VerifyCodeChangeCommand extends Command
{
    private $em;
    private $params;
    private $twig;
    private $mailer;
    private $token_storage;
    private $authorization_checker;
    private $router;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EngineInterface $twig,
        MailerInterface $mailer,
        TokenStorageInterface $token_storage,
        AuthorizationCheckerInterface $authorization_checker,
        RouterInterface $router
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->token_storage = $token_storage;
        $this->authorization_checker = $authorization_checker;
        $this->router = $router;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:code:verify_change')
            ->setDescription('Send reminder to validate code change if necessary')
            ->setHelp('This command send email to the user who generate the last code if he did not validate on the app that he actually change physically the code')
            ->addOption('last_run','', InputOption::VALUE_OPTIONAL, 'fréquence de cette commande, pour ne pas envoyer plusieurs fois le mail, en heures',24)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        # FIXME: this->getContainer ne fonctionne plus en symfony 4+
        # (utilisé plus bas dans le code)
        $this->getContainer()->get('App\Helper\SwipeCard');

        $last_run = $input->getOption('last_run');
        $last_run_date = new \DateTime();
        $last_run_date->modify("-".$last_run." hours");

        ////////////////////////
        $codeRepository = $this->em->getRepository('App:Code');
        $qb = $codeRepository
            ->createQueryBuilder('c');
        $qb->where('c.closed = :closed')
            ->setParameter('closed',0)
            ->addOrderBy('c.createdAt','DESC');
        $codes = $qb->getQuery()->getResult();

        if (count($codes)>1){ //more than one open code
            $output->writeln('<fg=cyan;>'.'more than one open code found ('.count($codes).')</>');
            $last =  $qb->setMaxResults(1)->getQuery()->getSingleResult();
            $output->writeln('<fg=cyan;>'.'last register is '.'</>'.'<fg=yellow;>'.$last->getRegistrar().'</>');
            if ($last->getCreatedAt() > $last_run_date){
                $token = new UsernamePasswordToken($last->getRegistrar(), $last->getRegistrar()->getPassword(), "main", $last->getRegistrar()->getRoles());
                $this->token_storage->setToken($token);
                $one_old_code_is_still_visible = false;
                foreach ($codes as $code){
                    if ($code != $last){
                        $one_old_code_is_still_visible = $one_old_code_is_still_visible || $this->authorization_checker->isGranted(CodeVoter::VIEW, $code);
                    }
                }
                if ($one_old_code_is_still_visible){
                    $code_change_done_url = $this->router->generate(
                        'code_change_done',
                        array(
                            'token' => $this->getContainer()->get('App\Helper\SwipeCard')->vigenereEncode($last->getRegistrar()->getUsername() . ',code:' . $last->getId())
                        ), UrlGeneratorInterface::ABSOLUTE_URL
                    );
                    $shiftEmail = $this->params->get('emails.shift');
                    $reminder = (new Email())
                        ->suject('[ESPACE MEMBRES] As tu réussi à changer le code du boîtier ?')
                        ->from(new Address($shiftEmail['address'], $shiftEmail['from_name']))
                        ->to($last->getRegistrar()->getEmail())
                        ->html(
                            $this->twig->render(
                                'emails/code_need_change_confirmation.html.twig',
                                array('code' => $last,'changeCodeUrl' => $code_change_done_url)
                            )
                        );
                    $this->mailer->send($reminder);
                    $message = 'email envoyé';
                    $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
                }else{
                    $output->writeln('<fg=magenta;>'.'codes that are still open are not visible for this user'.'</>');
                    $output->writeln('<fg=cyan;>>>></><fg=green;> no mail send </>');
                }
            }else{
                $output->writeln('<fg=magenta;>'.'code is too old, no warning send'.'</>');
                $output->writeln('<fg=cyan;>generate at : '.$last->getCreatedAt()->format('d M Y H:i').' < last cmd run : '.$last_run_date->format('d M Y H:i').'</>');
            }
        }

        return 0;
    }
}
