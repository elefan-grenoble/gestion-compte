<?php

namespace AppBundle\EventListener;

use AppBundle\Event\CodeNewEvent;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\MemberCycleHalfEvent;
use AppBundle\Event\ShiftBookedEvent;
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

    public function __construct(Swift_Mailer $mailer, Logger $logger, Container $container)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
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
     * @param MemberCycleEndEvent $event
     * @throws \Exception
     */
    public function onMemberCycleEnd(MemberCycleEndEvent $event)
    {
        $this->logger->info("Emailing Listener: onMemberCycleStart");

        $user = $event->getUser();
        $date = $event->getDate();

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        // user wont be frozen for this cycle && not a fresh new user && user still have to book
        if (!$user->getFrozenChange() && $user->getFirstShiftDate() < $date && $user->getCycleShiftsDuration() < $this->due_duration_by_cycle) {
            $mail = (new \Swift_Message('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux'))
                ->setFrom($this->container->getParameter('shift_mailer_user'))
                ->setTo($user->getEmail())
                ->setBody(
                    $this->container->get('twig')->render(
                        'emails/cycle_start.html.twig',
                        array('user' => $user, 'home_url' => $home_url)
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

        $user = $event->getUser();
        $date = $event->getDate();

        $router = $this->container->get('router');
        $home_url = $router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        if ($user->getFirstShiftDate() < $date && $user->getCycleShiftsDuration() < $this->due_duration_by_cycle) { //only if user still have to book
            $mail = (new \Swift_Message('[ESPACE MEMBRES] déjà la moitié de ton cycle, un tour sur ton espace membre ?'))
                ->setFrom($this->container->getParameter('shift_mailer_user'))
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/cycle_half.html.twig',
                        array('user' => $user, 'home_url' => $home_url)
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
        $code_change_done_url = $router->generate('code_change_done', array('token'=>$this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($code->getRegistrar()->getUsername().',code:'.$code->getId())), UrlGeneratorInterface::ABSOLUTE_URL);

        if (!$display) { //use smartphone
            $notify = (new \Swift_Message('[ESPACE MEMBRES] Nouveau code boîtier clefs'))
                ->setFrom($this->container->getParameter('transactional_mailer_user'))
                ->setTo($code->getRegistrar()->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/new_code.html.twig',
                        array('code' => $code,'codes' => $old_codes,'changeCodeUrl' => $code_change_done_url)
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
