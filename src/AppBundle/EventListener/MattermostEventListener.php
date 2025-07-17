<?php

namespace AppBundle\EventListener;

use AppBundle\Event\ShiftAlertsMattermostEvent;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\HttpClient;

class MattermostEventListener
{
    protected $em;
    protected $logger;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, Logger $logger, Container $container)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $locale = $this->container->getParameter('locale');
        setlocale(LC_TIME, $locale);
    }

    /**
     * @param ShiftAlertsMattermostEvent $event
     * @throws \Exception
     */
    public function onShiftAlerts(ShiftAlertsMattermostEvent $event)
    {
        $this->logger->info("Mattermost Listener: onShiftAlerts");

        $alerts = $event->getAlerts();
        $date = $event->getDate();

        if ($alerts && $event->getMattermostHookUrl()) {
            $template = null;
            $dynamicContent = $this->em->getRepository('AppBundle:DynamicContent')->findOneByCode($event->getTemplate());
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
