<?php

namespace App\EventListener;

use App\Entity\HelloassoPayment;
use App\Entity\Registration;
use App\Entity\User;
use App\Event\HelloassoEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Swift_Mailer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HelloassoEventListener
{
    protected $_em;
    protected $container;
    protected $mailer;
    private $memberEmail;

    public function __construct(EntityManager $entityManager, Container $container,Swift_Mailer $mailer,$memberEmail)
    {
        $this->_em = $entityManager;
        $this->container = $container;
        $this->mailer = $mailer;
        $this->memberEmail = $memberEmail;
    }

    public function onPaymentAfterSave(HelloassoEvent $event)
    {
        $payment = $event->getPayment();
        /** @var User $user */
        $user = $this->_em->getRepository('App:User')->findOneBy(array('email' => strtolower($payment->getEmail())));
        if ($user){
            $this->linkPaymentToUser($user,$payment);
        } else {
            $url = $this->container->get('router')->generate('helloasso_resolve_orphan', array(
                'id' => $payment->getId(),
                'code' => urlencode($this->container->get('App\Helper\SwipeCard')->vigenereEncode($payment->getEmail()))
                ),UrlGeneratorInterface::ABSOLUTE_URL);

            $needInfo = (new \Swift_Message('Merci '.$payment->getPayerFirstName().', mais qui es-tu ?'))
                ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
                ->setTo($payment->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/helloasso_wrong_email.html.twig',
                        array(
                            'firstname' => $payment->getPayerFirstName(),
                            'email' => $payment->getEmail(),
                            'project_name' => $this->container->getParameter('project_name'),
                            'url' => $url
                        )
                    ),
                    'text/html'
                );
            $this->mailer->send($needInfo);
            //throw new \LogicException('user not found');
        }
    }

    public function onOrphanSolve(HelloassoEvent $event)
    {
        $payment = $event->getPayment();
        $user = $event->getUser();
        if ($user) {
            $this->linkPaymentToUser($user, $payment);
        }
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
        if ($this->container->has('templating')) {
            return $this->container->get('templating')->render($view, $parameters);
        }

        if (!$this->container->has('twig')) {
            throw new \LogicException('You can not use the "renderView" method if the Templating Component or the Twig Bundle are not available.');
        }

        return $this->container->get('twig')->render($view, $parameters);
    }

    protected function linkPaymentToUser(User $user,HelloassoPayment $payment){
        $beneficiary = $user->getBeneficiary();
        if ($beneficiary) {
            $membership = $beneficiary->getMembership();
            if (!$this->container->get('membership_service')->canRegister($membership)) {
                //throw new \LogicException('user cannot register yet');
                $this->container->get('event_dispatcher')->dispatch(HelloassoEvent::TOO_EARLY,new HelloassoEvent($payment,$user));
            } else {
                $registration = new Registration();
                $registration->setAmount($payment->getAmount());
                $registration->setCreatedAt($payment->getDate()); //created at payment date

                if ($membership->getLastRegistration()){
                    $expire = clone $this->container->get('membership_service')->getExpire($membership);
                    if ($expire > $payment->getDate()) // not yet expired
                        $registration->setDate($expire);
                    else
                        $registration->setDate($payment->getDate());
                }else{ //first registration
                    $registration->setDate($payment->getDate());
                }

                $registration->setHelloassoPayment($payment);
                $registration->setMode(Registration::TYPE_HELLOASSO);
                $registration->setMembership($membership);

                $this->_em->persist($registration);
                $payment->setRegistration($registration);
                $membership->addRegistration($registration);

                if ($membership->isWithdrawn()){
                    $membership->setWithdrawn(false); //open
                }
                $this->_em->persist($membership);

                $this->_em->flush();

                $this->container->get('event_dispatcher')->dispatch(HelloassoEvent::RE_REGISTRATION_SUCCESS,new HelloassoEvent($payment,$beneficiary->getUser()));
            }
        } else {
            throw new \LogicException('user without beneficiary');
        }
    }
}
