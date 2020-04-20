<?php

namespace AppBundle\EventListener;

use AppBundle\Event\AnonymousBeneficiaryCreatedEvent;
use AppBundle\Event\AnonymousBeneficiaryRecallEvent;
use AppBundle\Event\BeneficiaryAddEvent;
use AppBundle\Event\CodeNewEvent;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Event\MemberCreatedEvent;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\MemberCycleHalfEvent;
use AppBundle\Event\MemberCycleStartEvent;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Event\ShiftDismissedEvent;
use Monolog\Logger;
use Swift_Mailer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailingEventListener
{
    protected $mailer;
    protected $logger;
    protected $container;
    protected $due_duration_by_cycle;
    private $memberEmail;
    private $shiftEmail;
    private $wikiKeysUrl;

    public function __construct(Swift_Mailer $mailer, Logger $logger, Container $container, $memberEmail, $shiftEmail, $wikiKeysUrl)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->memberEmail = $memberEmail;
        $this->shiftEmail = $shiftEmail;
        $this->wikiKeysUrl = $wikiKeysUrl;
    }

    /**
     * @param AnonymousBeneficiaryCreatedEvent $event
     * @throws \Exception
     */
    public function onAnonymousBeneficiaryCreated(AnonymousBeneficiaryCreatedEvent $event)
    {
        $this->logger->info("Emailing Listener: onAnonymousBeneficiaryCreated");

        $email = $event->getAnonymousBeneficiary()->getEmail();

        if (!$event->getAnonymousBeneficiary()->getJoinTo()){
            $url = $this->container->get('router')->generate('member_new', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }else{
            $url = $this->container->get('router')->generate('member_add_beneficiary', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $needInfo = (new \Swift_Message('Bienvenue à '.$this->container->getParameter('project_name').', tu te présentes ?'))
            ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
            ->setTo($email)
            ->setBody(
                $this->renderView(
                    'emails/needInfo.html.twig',
                    array(
                        'register_url' => $url
                    )
                ),
                'text/html'
            );
        $this->mailer->send($needInfo);

    }

    /**
     * @param AnonymousBeneficiaryRecallEvent $event
     * @throws \Exception
     */
    public function onAnonymousBeneficiaryRecall(AnonymousBeneficiaryRecallEvent $event)
    {
        $this->logger->info("Emailing Listener: onAnonymousBeneficiaryRecall");

        $email = $event->getAnonymousBeneficiary()->getEmail();

        if (!$event->getAnonymousBeneficiary()->getJoinTo()){
            $url = $this->container->get('router')->generate('member_new', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }else{
            $url = $this->container->get('router')->generate('member_add_beneficiary', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $needInfoRecall = (new \Swift_Message('Bienvenue à '.$this->container->getParameter('project_name').', souhaites-tu te présenter ?'))
            ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
            ->setTo($email)
            ->setBody(
                $this->renderView(
                    'emails/needInfoRecall.html.twig',
                    array(
                        'register_url' => $url,
                        'rdate' => $event->getAnonymousBeneficiary()->getCreatedAt()
                    )
                ),
                'text/html'
            );
        $this->mailer->send($needInfoRecall);
    }

    /**
     * @param BeneficiaryAddEvent $event
     * @throws \Exception
     */
    public function onBeneficiaryAdd(BeneficiaryAddEvent $event){
        $this->logger->info("Emailing Listener: onBeneficiaryAdd");

        $beneficiary = $event->getBeneficiary();

        $owner = $beneficiary->getMembership()->getMainBeneficiary();
        $newBuddy = (new \Swift_Message($beneficiary->getFirstname().' a été ajouté à ton compte '.$this->container->getParameter('project_name')))
            ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
            ->setTo($owner->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/new_beneficiary.html.twig',
                    array(
                        'owner' => $owner,
                        'beneficiary' => $beneficiary,
                    )
                ),
                'text/html'
            );
        $this->mailer->send($newBuddy);

    }

    /**
     * @param MemberCreatedEvent $event
     * @throws \Exception
     */
    public function onMemberCreated(MemberCreatedEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCreated");

        //
    }

    /**
     * @param HelloassoEvent $event
     * @throws \Exception
     */
    public function onHelloassoRegistrationSuccess(HelloassoEvent $event)
    {
        $user = $event->getUser();
        $payment = $event->getPayment();

        if ($user->getBeneficiary()->getMembership()->getRegistrations()->count()>1){
            $thanks = (new \Swift_Message('[ESPACE MEMBRES] Re-adhésion helloasso bien reçue !'))
                ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/reregistration.html.twig',
                        array('beneficiary' => $user->getBeneficiary(),
                            'payment' => $payment)
                    ),
                    'text/html'
                );
        }else{
            $thanks = (new \Swift_Message('[ESPACE MEMBRES] Adhésion helloasso bien reçue !'))
                ->setFrom($this->container->getParameter('transactional_mailer_user'))
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/registration.html.twig',
                        array('beneficiary' => $user->getBeneficiary(),
                            'payment' => $payment)
                    ),
                    'text/html'
                );
        }

        $this->mailer->send($thanks);
    }

    /**
     * @param HelloassoEvent $event
     * @throws \Exception
     */
    public function onHelloassoTooEarly(HelloassoEvent $event)
    {
        $user = $event->getUser();
        $payment = $event->getPayment();

        $membershipService = $this->container->get('membership_service');
        $membership = $user->getBeneficiary()->getMembership();
        $membershipExpiration = $membershipService->getExpire($membership);

        try {
            $oups = (new \Swift_Message('[ESPACE MEMBRES] Oups ! il et trop tôt pour réadhérer !'))
                ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/too_early_registration.html.twig',
                        array(
                            'beneficiary' => $user->getBeneficiary(),
                            'payment' => $payment,
                            'membershipExpiration' => $membershipExpiration
                        )
                    ),
                    'text/html'
                );
        } catch (\Exception $e){
            die($e->getMessage());
        }
        $this->mailer->send($oups);
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Exception
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $shift = $event->getShift();

        $archive = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
            ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
            ->setTo($this->shiftEmail['address'])
            ->setReplyTo($shift->getShifter()->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/new_booking.html.twig',
                    array('shift' => $shift)
                ),
                'text/html'
            );
        $this->mailer->send($archive);
    }

    /**
     * @param ShiftDeletedEvent $event
     * @throws \Exception
     */
    public function onShiftDeleted(ShiftDeletedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftDeleted");
        $shift = $event->getShift();
        if ($shift->getShifter()) { //warn shifter
            $warn = (new \Swift_Message('[ESPACE MEMBRES] Crénéau supprimé'))
                ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                ->setTo($shift->getShifter()->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/deleted_shift.html.twig',
                        array('shift' => $shift)
                    ),
                    'text/html'
                );
            $this->mailer->send($warn);
        }
    }

    /**
     * @param ShiftDismissedEvent $event
     * @throws \Exception
     */
    public function onShiftDismissed(ShiftDismissedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftDismissed");
        $shift = $event->getShift();
        $beneficiary = $event->getBeneficiary();
        if ($shift->getIsUpcoming()) {
            $warn = (new \Swift_Message("[ESPACE MEMBRES] Crénéau annulé moins de 48 heures à l'avance"))
                ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                ->setTo($this->shiftEmail['address'])
                ->setReplyTo($beneficiary->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/dismissed_shift.html.twig',
                        array(
                            'shift' => $shift,
                            'beneficiary' => $beneficiary,
                            'reason' => $event->getReason()
                        )
                    ),
                    'text/html'
                );
            $this->mailer->send($warn);
        }
    }

    /**
     * @param MemberCycleStartEvent $event
     * @throws \Exception
     */
    public function onMemberCycleStart(MemberCycleStartEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCycleStart");

        $membership = $event->getMembership();
        $date = $event->getDate();

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        // member wont be frozen for this cycle && not a fresh new member && member still have to book
        if (!$membership->getFrozen() && $membership->getFirstShiftDate() < $date && $membership->getCycleShiftsDuration() < $this->due_duration_by_cycle) {
            foreach ($membership->getBeneficiaries() as $beneficiary){
                $mail = (new \Swift_Message('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux'))
                    ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                    ->setTo($beneficiary->getEmail())
                    ->setBody(
                        $this->container->get('twig')->render(
                            'emails/cycle_start.html.twig',
                            array('beneficiary' => $beneficiary, 'home_url' => $home_url)
                        ),
                        'text/html'
                    );
                $this->mailer->send($mail);
            }

        }
    }

    /**
     * @param MemberCycleHalfEvent $event
     * @throws \Exception
     */
    public function onMemberCycleHalf(MemberCycleHalfEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCycleHalf");

        $membership = $event->getMembership();
        $date = $event->getDate();

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        if ($membership->getFirstShiftDate() < $date && $membership->getCycleShiftsDuration() < $this->due_duration_by_cycle) { //only if member still have to book
            $mail = (new \Swift_Message('[ESPACE MEMBRES] déjà la moitié de ton cycle, un tour sur ton espace membre ?'))
                ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                ->setTo($membership->getMainBeneficiary()->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/cycle_half.html.twig',
                        array('membership' => $membership, 'home_url' => $home_url)
                    ),
                    'text/html'
                );
            $this->mailer->send($mail);
        }
    }

    /**
     * @param CodeNewEvent $event
     * @throws \Exception
     */
    public function onCodeNew(CodeNewEvent $event)
    {
        $this->logger->info("Emailing Listener: onCodeNew");
        $code = $event->getCode();
        $old_codes = $event->getOldCodes();

        $router = $this->container->get('router');
        $code_change_done_url = $router->generate('code_change_done', array('token' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($code->getRegistrar()->getUsername() . ',code:' . $code->getId())), UrlGeneratorInterface::ABSOLUTE_URL);

        $notify = (new \Swift_Message('[ESPACE MEMBRES] Nouveau code boîtier clefs'))
            ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
            ->setTo($code->getRegistrar()->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/code_new.html.twig',
                    array(
                        'code' => $code,
                        'codes' => $old_codes,
                        'changeCodeUrl' => $code_change_done_url,
                        'wiki_keys_url' => $this->wikiKeysUrl
                    )
                ),
                'text/html'
            );
        $this->mailer->send($notify);

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
}
