<?php

namespace App\EventListener;

use App\Entity\HelloassoPayment;
use App\Entity\Registration;
use App\Entity\User;
use App\Event\HelloassoEvent;
use App\Helper\SwipeCard;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Swift_Mailer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\EngineInterface;

class HelloassoEventListener
{
    protected $_em;
    protected $mailer;
    private $memberEmail;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var SwipeCard
     */
    private $swipeCard;
    /**
     * @var string
     */
    private $projectName;
    /**
     * @var EngineInterface
     */
    private $templating;
    /**
     * @var MembershipService
     */
    private $membershipService;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityManagerInterface $entityManager,
        Swift_Mailer $mailer,
        $memberEmail,
        UrlGeneratorInterface $urlGenerator,
        SwipeCard $swipeCard,
        string $projectName,
        EngineInterface $templating,
        MembershipService $membershipService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->_em = $entityManager;
        $this->mailer = $mailer;
        $this->memberEmail = $memberEmail;
        $this->urlGenerator = $urlGenerator;
        $this->swipeCard = $swipeCard;
        $this->projectName = $projectName;
        $this->templating = $templating;
        $this->membershipService = $membershipService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function onPaymentAfterSave(HelloassoEvent $event)
    {
        $payment = $event->getPayment();
        /** @var User $user */
        $user = $this->_em->getRepository('App:User')->findOneBy(array('email' => strtolower($payment->getEmail())));
        if ($user){
            $this->linkPaymentToUser($user,$payment);
        } else {
            $url = $this->urlGenerator->generate('helloasso_resolve_orphan', array(
                'id' => $payment->getId(),
                'code' => urlencode($this->swipeCard->vigenereEncode($payment->getEmail()))
                ),UrlGeneratorInterface::ABSOLUTE_URL);

            $needInfo = (new \Swift_Message('Merci '.$payment->getPayerFirstName().', mais qui es-tu ?'))
                ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
                ->setTo($payment->getEmail())
                ->setBody(
                    $this->templating->render(
                        'emails/helloasso_wrong_email.html.twig',
                        array(
                            'firstname' => $payment->getPayerFirstName(),
                            'email' => $payment->getEmail(),
                            'project_name' => $this->projectName,
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

    protected function linkPaymentToUser(User $user,HelloassoPayment $payment){
        $beneficiary = $user->getBeneficiary();
        if ($beneficiary) {
            $membership = $beneficiary->getMembership();
            if (!$this->membershipService->canRegister($membership)) {
                //throw new \LogicException('user cannot register yet');
                $this->eventDispatcher->dispatch(HelloassoEvent::TOO_EARLY,new HelloassoEvent($payment,$user));
            } else {
                $registration = new Registration();
                $registration->setAmount($payment->getAmount());
                $registration->setCreatedAt($payment->getDate()); //created at payment date

                if ($membership->getLastRegistration()){
                    $expire = clone $this->membershipService->getExpire($membership);
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

                $this->eventDispatcher->dispatch(HelloassoEvent::RE_REGISTRATION_SUCCESS,new HelloassoEvent($payment,$beneficiary->getUser()));
            }
        } else {
            throw new \LogicException('user without beneficiary');
        }
    }
}
