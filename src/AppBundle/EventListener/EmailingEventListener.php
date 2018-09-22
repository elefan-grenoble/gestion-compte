<?php

namespace AppBundle\EventListener;

use AppBundle\Event\ShiftBookedEvent;
use Swift_Mailer;
use Symfony\Component\DependencyInjection\Container;

class EmailingEventListener
{
    protected $_mailer;
    protected $_container;

    public function __construct(Swift_Mailer $mailer, Container $container)
    {
        $this->_mailer = $mailer;
        $this->_container = $container;
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Exception
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $shift = $event->getShift();

        $archive = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
            ->setFrom('membres@lelefan.org')
            ->setTo('creneaux@lelefan.org')
            ->setReplyTo($shift->getShifter()->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/new_booking.html.twig',
                    array('shift' => $shift)
                ),
                'text/html'
            );
        $this->_mailer->send($archive);
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
        if ($this->_container->has('templating')) {
            return $this->_container->get('templating')->render($view, $parameters);
        }

        if (!$this->_container->has('twig')) {
            throw new \LogicException('You can not use the "renderView" method if the Templating Component or the Twig Bundle are not available.');
        }

        return $this->_container->get('twig')->render($view, $parameters);
    }
}
