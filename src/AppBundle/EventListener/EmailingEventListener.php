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
use AppBundle\Event\ShiftReservedEvent;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Swift_Mailer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailingEventListener
{
    protected $em;
    protected $logger;
    protected $container;
    protected $mailer;
    protected $due_duration_by_cycle;
    protected $member_email;
    protected $shift_email;
    protected $wiki_keys_url;
    protected $reserve_new_shift_to_prior_shifter_delay;

    public function __construct(EntityManagerInterface $entityManager, Logger $logger, Container $container, Swift_Mailer $mailer)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $this->mailer = $mailer;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->member_email = $this->container->getParameter('emails.member');
        $this->shift_email = $this->container->getParameter('emails.shift');
        $this->wiki_keys_url = $this->container->getParameter('wiki_keys_url');
        $this->reserve_new_shift_to_prior_shifter_delay = $this->container->getParameter('reserve_new_shift_to_prior_shifter_delay');
    }

    /**
     * @param AnonymousBeneficiaryCreatedEvent $event
     * @throws \Exception
     */
    public function onAnonymousBeneficiaryCreated(AnonymousBeneficiaryCreatedEvent $event)
    {
        $this->logger->info("Emailing Listener: onAnonymousBeneficiaryCreated");

        $emailObject = 'Bienvenue à ' . $this->container->getParameter('project_name') . ', tu te présentes ?';
        $emailTo = $event->getAnonymousBeneficiary()->getEmail();

        $dynamicContent = $this->em->getRepository('AppBundle:DynamicContent')->findOneByCode("PRE_MEMBERSHIP_EMAIL")->getContent();

        $router = $this->container->get('router');
        if (!$event->getAnonymousBeneficiary()->getJoinTo()) {
            $url = $router->generate('member_new', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            $url = $router->generate('member_add_beneficiary', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->member_email['address'], $this->member_email['from_name'])
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'emails/needInfo.html.twig',
                    array(
                        'register_url' => $url,
                        'dynamicContent' => $dynamicContent,
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);
    }

    /**
     * @param AnonymousBeneficiaryRecallEvent $event
     * @throws \Exception
     */
    public function onAnonymousBeneficiaryRecall(AnonymousBeneficiaryRecallEvent $event)
    {
        $this->logger->info("Emailing Listener: onAnonymousBeneficiaryRecall");

        $emailObject = 'Bienvenue à ' . $this->container->getParameter('project_name') . ', souhaites-tu te présenter ?';
        $emailTo = $event->getAnonymousBeneficiary()->getEmail();

        $dynamicContent = $this->em->getRepository('AppBundle:DynamicContent')->findOneByCode("PRE_MEMBERSHIP_EMAIL")->getContent();

        $router = $this->container->get('router');
        if (!$event->getAnonymousBeneficiary()->getJoinTo()) {
            $url = $router->generate('member_new', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            $url = $router->generate('member_add_beneficiary', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)),UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->member_email['address'], $this->member_email['from_name'])
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'emails/needInfoRecall.html.twig',
                    array(
                        'register_url' => $url,
                        'dynamicContent' => $dynamicContent,
                        'rdate' => $event->getAnonymousBeneficiary()->getCreatedAt()
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);
    }

    /**
     * @param BeneficiaryAddEvent $event
     * @throws \Exception
     */
    public function onBeneficiaryAdd(BeneficiaryAddEvent $event)
    {
        $this->logger->info("Emailing Listener: onBeneficiaryAdd");

        $beneficiary = $event->getBeneficiary();
        $owner = $beneficiary->getMembership()->getMainBeneficiary();

        $emailObject = $beneficiary->getFirstname() . ' a été ajouté à ton compte ' . $this->container->getParameter('project_name');
        $emailTo = $owner->getEmail();

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->member_email['address'], $this->member_email['from_name'])
            ->setTo($emailTo)
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

        $this->mailer->send($email);
    }

    /**
     * @param MemberCreatedEvent $event
     * @throws \Exception
     */
    public function onMemberCreated(MemberCreatedEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCreated");

        // TODO ?
    }

    /**
     * @param HelloassoEvent $event
     * @throws \Exception
     */
    public function onHelloassoRegistrationSuccess(HelloassoEvent $event)
    {
        $user = $event->getUser();
        $payment = $event->getPayment();

        $emailTo = $user->getEmail();

        if ($user->getBeneficiary()->getMembership()->getRegistrations()->count()>1) {
            $emailObject = '[ESPACE MEMBRES] Re-adhésion helloasso bien reçue !';
            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->member_email['address'], $this->member_email['from_name'])
                ->setTo($emailTo)
                ->setBody(
                    $this->renderView(
                        'emails/reregistration.html.twig',
                        array(
                            'beneficiary' => $user->getBeneficiary(),
                            'payment' => $payment)
                    ),
                    'text/html'
                );
        } else {
            $emailObject = '[ESPACE MEMBRES] Adhésion helloasso bien reçue !';
            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->container->getParameter('transactional_mailer_user'))
                ->setTo($emailTo)
                ->setBody(
                    $this->renderView(
                        'emails/registration.html.twig',
                        array(
                            'beneficiary' => $user->getBeneficiary(),
                            'payment' => $payment)
                    ),
                    'text/html'
                );
        }

        $this->mailer->send($email);
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

        $emailObject = '[ESPACE MEMBRES] Oups ! il et trop tôt pour ré-adhérer !';
        $emailTo = $user->getEmail();

        try {
            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->member_email['address'], $this->member_email['from_name'])
                ->setTo($emailTo)
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
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        $this->mailer->send($email);
    }

    /**
     * @param ShiftReservedEvent $event
     * @throws \Exception
     */
    public function onShiftReserved(ShiftReservedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftReserved");

        $shift = $event->getShift();
        $formerShift = $event->getFormerShift();
        $beneficiary = $shift->getLastShifter();

        $emailObject = '[ESPACE MEMBRES] Reprends ton créneau du ' . strftime("%e %B", $formerShift->getStart()->getTimestamp()) . ' dans ' . $d . ' jours';
        $emailTo = $beneficiary->getEmail();

        $d = (date_diff(new \DateTime('now'),$shift->getStart())->format("%a"));

        $router = $this->container->get('router');
        $accept_url = $router->generate('shift_accept_reserved', array('id' => $shift->getId(), 'token' => $shift->getTmpToken($beneficiary->getId())), UrlGeneratorInterface::ABSOLUTE_URL);
        $reject_url = $router->generate('shift_reject_reserved', array('id' => $shift->getId(), 'token' => $shift->getTmpToken($beneficiary->getId())), UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'emails/shift_reserved.html.twig',
                    array(
                        'shift' => $shift,
                        'oldshift' => $formerShift,
                        'days' => $d,
                        'reserve_new_shift_to_prior_shifter_delay' => $this->reserve_new_shift_to_prior_shifter_delay,
                        'accept_url' => $accept_url,
                        'reject_url' => $reject_url,
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Exception
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftBooked");

        $shift = $event->getShift();
        $beneficiary = $shift->getShifter();

        // send a "confirmation" e-mail to the beneficiary
        $emailObject = '[ESPACE MEMBRES] Réservation de ton créneau confirmée';
        $emailTo = $beneficiary->getEmail();

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'emails/shift_booked_confirmation.html.twig',
                    array(
                        'shift' => $shift
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);

        // send an "archive" e-mail to the admin
        $emailObject = '[ESPACE MEMBRES] Réservation de ton créneau confirmée';
        $emailTo = $beneficiary->getEmail();

        $email = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
            ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
            ->setTo($this->shift_email['address'])
            ->setReplyTo($beneficiary->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/shift_booked_archive.html.twig',
                    array(
                        'shift' => $shift
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);
    }

    /**
     * @param ShiftFreedEvent $event
     * @throws \Exception
     */
    public function onShiftFreed(ShiftFreedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftFreed");

        $shift = $event->getShift();
        $beneficiary = $event->getBeneficiary();

        // send a notification e-mail to the beneficiary
        if ($beneficiary) {
            $emailObject = '[ESPACE MEMBRES] Créneau libéré';
            $emailTo = $beneficiary->getEmail();

            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
                ->setTo($emailTo)
                ->setBody(
                    $this->renderView(
                        'emails/shift_freed.html.twig',
                        array(
                            'shift' => $shift,
                            'beneficiary' => $beneficiary
                        )
                    ),
                    'text/html'
                );

            $this->mailer->send($email);
        }
    }

    /**
     * @param ShiftDeletedEvent $event
     * @throws \Exception
     */
    public function onShiftDeleted(ShiftDeletedEvent $event)
    {
        $this->logger->info("Emailing Listener: onShiftDeleted");

        $shift = $event->getShift();
        $beneficiary = $event->getBeneficiary();

        // send a notification e-mail to the beneficiary
        if ($beneficiary) {
            $emailObject = '[ESPACE MEMBRES] Créneau supprimé';
            $emailTo = $beneficiary->getEmail();

            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
                ->setTo($emailTo)
                ->setBody(
                    $this->renderView(
                        'emails/shift_deleted.html.twig',
                        array(
                            'shift' => $shift,
                            'beneficiary' => $beneficiary
                        )
                    ),
                    'text/html'
                );

            $this->mailer->send($email);
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
        $currentCycleShifts = $event->getCurrentCycleShifts();

        $emailObject = '[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux';
        // $emailTo: see for loop

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        // Compute cycleShiftsDuration
        $cycleShiftsDuration = 0;
        foreach ($currentCycleShifts as $shift) {
            $cycleShiftsDuration += $shift->getDuration();
        }
        // member wont be frozen for this cycle && not a fresh new member && member still have to book
        if (!$membership->getFrozen() && $membership->getFirstShiftDate() < $date && $cycleShiftsDuration < $this->due_duration_by_cycle) {
            foreach ($membership->getBeneficiaries() as $beneficiary) {
                $email = (new \Swift_Message($emailObject))
                    ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
                    ->setTo($beneficiary->getEmail())
                    ->setBody(
                        $this->container->get('twig')->render(
                            'emails/cycle_start.html.twig',
                            array(
                                'beneficiary' => $beneficiary,
                                'home_url' => $home_url
                            )
                        ),
                        'text/html'
                    );

                $this->mailer->send($email);
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
        $currentCycleShifts = $event->getCurrentCycleShifts();

        $emailObject = '[ESPACE MEMBRES] déjà la moitié de ton cycle, un tour sur ton espace membre ?';
        $emailTo = $membership->getMainBeneficiary()->getEmail();

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        // Compute cycleShiftsDuration
        $cycleShiftsDuration = 0;
        foreach ($currentCycleShifts as $shift) {
            $cycleShiftsDuration += $shift->getDuration();
        }
        if ($membership->getFirstShiftDate() < $date && $cycleShiftsDuration < $this->due_duration_by_cycle) { //only if member still have to book
            $email = (new \Swift_Message($emailObject))
                ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
                ->setTo($membership->getMainBeneficiary()->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/cycle_half.html.twig',
                        array(
                            'membership' => $membership,
                            'home_url' => $home_url
                        )
                    ),
                    'text/html'
                );

            $this->mailer->send($email);
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

        $emailObject = '[ESPACE MEMBRES] Nouveau code boîtier clefs';
        $emailTo = $code->getRegistrar()->getEmail();

        $router = $this->container->get('router');
        $code_change_done_url = $router->generate('code_change_done', array('token' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($code->getRegistrar()->getUsername() . ',code:' . $code->getId())), UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new \Swift_Message($emailObject))
            ->setFrom($this->shift_email['address'], $this->shift_email['from_name'])
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'emails/code_new.html.twig',
                    array(
                        'code' => $code,
                        'codes' => $old_codes,
                        'changeCodeUrl' => $code_change_done_url,
                        'wiki_keys_url' => $this->wiki_keys_url
                    )
                ),
                'text/html'
            );

        $this->mailer->send($email);
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
