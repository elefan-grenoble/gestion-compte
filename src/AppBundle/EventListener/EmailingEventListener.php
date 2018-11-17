<?php

namespace AppBundle\EventListener;

use AppBundle\Event\CodeNewEvent;
use AppBundle\Event\MemberCreatedEvent;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\MemberCycleHalfEvent;
use AppBundle\Event\MemberCycleStartEvent;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
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
    private $wikiKeysUrl;

    public function __construct(Swift_Mailer $mailer, Logger $logger, Container $container, $memberEmail, $wikiKeysUrl)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->memberEmail = $memberEmail;
        $this->wikiKeysUrl = $wikiKeysUrl;
    }

    /**
     * @param MemberCreatedEvent $event
     * @throws \Exception
     */
    public function onMemberCreated(MemberCreatedEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCreated");

        $beneficiaries = $event->getMembership()->getBeneficiaries();

        foreach ($beneficiaries as $beneficiary) {
            $welcome = (new \Swift_Message('Bienvenue à l\'éléfàn'))
                ->setFrom($this->memberEmail['address'], $this->memberEmail['from_name'])
                ->setTo($beneficiary->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/welcome.html.twig',
                        array('beneficiary' => $beneficiary)
                    ),
                    'text/html'
                );
            $this->mailer->send($welcome);
        }
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Exception
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $shift = $event->getShift();

        $archive = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
            ->setFrom($this->container->getParameter('transactional_mailer_user'))
            ->setTo($this->container->getParameter('shift_mailer_user'))
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
        $shift = $event->getShift();
        if ($shift->getShifter()) { //warn shifter
            $warn = (new \Swift_Message('[ESPACE MEMBRES] Crénéau supprimé'))
                ->setFrom($this->container->getParameter('shift_mailer_user'))
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
            $mail = (new \Swift_Message('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux'))
                ->setFrom($this->container->getParameter('shift_mailer_user'))
                ->setTo($membership->getMainBeneficiary()->getEmail())
                ->setBody(
                    $this->container->get('twig')->render(
                        'emails/cycle_start.html.twig',
                        array('membership' => $membership, 'home_url' => $home_url)
                    ),
                    'text/html'
                );
            $this->mailer->send($mail);
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
                ->setFrom($this->container->getParameter('shift_mailer_user'))
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
        $display = $event->getDisplay();

        $router = $this->container->get('router');
        $code_change_done_url = $router->generate('code_change_done', array('token' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($code->getRegistrar()->getUsername() . ',code:' . $code->getId())), UrlGeneratorInterface::ABSOLUTE_URL);

        if (!$display) { //use smartphone
            $notify = (new \Swift_Message('[ESPACE MEMBRES] Nouveau code boîtier clefs'))
                ->setFrom($this->container->getParameter('transactional_mailer_user'))
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
