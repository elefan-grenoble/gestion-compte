<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Mailer\MailerInterface;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\EngineInterface;

class MailerService implements MailerInterface
{
    private $baseDomain;
    private $memberEmail;
    private $projectName;
    private $sendableEmails;
    private $entity_manager;
    private $mailer;
    private $router;
    private $templating;

    public function __construct(
        \Swift_Mailer $mailer,
        string $baseDomain,
        array $memberEmail,
        string $projectName,
        array $sendableEmails,
        EntityManagerInterface $entity_manager,
        UrlGeneratorInterface $router,
        EngineInterface $templating
    ) {
        $this->mailer = $mailer;
        $this->baseDomain = $baseDomain;
        $this->memberEmail = $memberEmail;
        $this->projectName = $projectName;
        $this->sendableEmails = $sendableEmails;
        $this->entity_manager = $entity_manager;
        $this->router = $router;
        $this->templating = $templating;
    }

    /**
     * Check if the given email is a temporary one
     * @param string $email
     * @return bool
     */
    public function isTemporaryEmail(string $email) : bool
    {
        $pattern = $this->getTemporaryEmailPattern();
        preg_match_all($pattern, $email, $matches, PREG_SET_ORDER, 0);
        return count($matches) > 0;
    }

    public function getAllowedEmails() : array
    {
        $return = array();
        foreach ($this->sendableEmails as $email){
            $key = $email['from_name'].' <'.$email['address'].'>';
            $return[$key] = $email['address'];
        }
        return $return;
    }

    private function getTemporaryEmailPattern() : string
    {
        return '/(membres\\+[0-9]+@' . preg_quote($this->baseDomain) . ')/i';
    }

    /**
     * Send an email to a user to confirm the account creation.
     *
     * @param UserInterface $user
     */
    public function sendConfirmationEmailMessage(UserInterface $user){
        $dynamicContent = $this->entity_manager->getRepository('App:DynamicContent')->findOneByCode("WELCOME_EMAIL")->getContent();

        $login_url = $url = $this->router->generate('fos_user_registration_confirm', array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);
        $welcome = (new \Swift_Message('Bienvenue à ' . $this->projectName))
            ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/welcome.html.twig',
                    array(
                        'user' => $user,
                        'dynamicContent' => $dynamicContent,
                        'login_url' => $login_url,
                    )
                ),
                'text/html'
            );
        $this->mailer->send($welcome);
    }

    /**
     * Send an email to a user to confirm the password reset.
     *
     * @param UserInterface $user
     */
    public function sendResettingEmailMessage(UserInterface $user){
        $confirmationUrl = $this->router->generate('fos_user_resetting_reset', array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);

        $forgot = (new \Swift_Message('Réinitialisation de ton mot de passe'))
            ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/forgot.html.twig',
                    array(
                        'user' => $user,
                        'confirmationUrl' => $confirmationUrl,
                    )
                ),
                'text/html'
            );
        $this->mailer->send($forgot);
    }


    /**
     * Returns a rendered view.
     *
     * @param string $view The view name
     * @param array $parameters An array of parameters to pass to the view
     *
     * @return string The rendered view
     * @throws \Exception
     */
    protected function renderView($view, array $parameters = array())
    {
        return $this->templating->render($view, $parameters);
    }
}