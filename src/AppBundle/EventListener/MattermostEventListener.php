<?php

namespace AppBundle\EventListener;

use AppBundle\Event\ShiftAlertsEvent;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Swift_Mailer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\HttpClient;

class MattermostEventListener
{
    protected $em;
    protected $logger;
    protected $container;
    protected $mailer;

    public function __construct(EntityManagerInterface $entityManager, Logger $logger, Container $container, Swift_Mailer $mailer)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $this->mailer = $mailer;
    }

    /**
     * @param ShiftAlertsEvent $event
     * @throws \Exception
     */
    public function onShiftAlerts(ShiftAlertsEvent $event)
    {
        $this->logger->info("Mattermost Listener: onShiftAlerts");

        $alerts = $event->getAlerts();
        $date = $event->getDate();

        if ($event->getMattermostHookUrl()) {
            $template = null;
            $dynamicContent = $this->em->getRepository('AppBundle:DynamicContent')->findOneByCode($event->getEmailTemplate());
            if ($dynamicContent) {
                $template = $this->container->get('twig')->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'markdown/shift_alerts_default.md.twig';
            }
            $content = $this->container->get('twig')->render(
                $template,
                array('alerts' => $alerts, 'date' => $date)
            );

            $client = HttpClient::create();
            $response = $client->request('POST', $event->getMattermostHookUrl(), [
                'json' => ['text' => $content]
            ]);
        }
    }
}
