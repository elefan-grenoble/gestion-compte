<?php

namespace AppBundle\Service;

use AppBundle\Entity\Code;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;

class CodeService
{

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * group codes by code device
     */
    public function groupCodesPerDevice($codes)
    {
        $codesByDevice = array();
        foreach ($codes as $code) {
            $id = null !== $code->getCodeDevice() ? $code->getCodeDevice()->getId() : '';
            if (!isset($codesByDevice[$id])) {
                $codesByDevice[$id] = array();
            }
            $codesByDevice[$id][] = $code;
        }
        return $codesByDevice;
    }

    public function generateIgloohomeLockCode($codeDevice, $code)
    {
        return $this->generateIgloohomeCode($codeDevice, $code->getStartDate(), $code->getEndDate(), $code->getDescription());
    }

    /**
     * Generate Igloohome code
     */
    public function generateIgloohomeCode($codeDevice, $start, $end, $description)
    {
        // Create a new temporary code using the Igloohome API
        $client = HttpClient::create(['headers' => ['X-IGLOOHOME-APIKEY' => $codeDevice->getIgloohomeApiKey()]]);
        $response = $client->request('POST', 'https://partnerapi.igloohome.co/v1/locks/' . $codeDevice->getIgloohomeLockId() . '/lockcodes', [
            'json' => [
                'durationCode' => 3,
                'startDate' => $start->format('c'),
                'endDate' => $end->format('c'),
                'description' => $description
            ]
        ]);

        $status = $response->getStatusCode();
        if ($status != 200) {
            $mailer = $this->container->get('mailer');
            $shiftEmail = $this->container->getParameter('emails.shift');
            $content = $response->getContent(false);
            $mail = (new \Swift_Message('[ESPACE MEMBRES] Echec de création du code du boitier'))
                ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                ->setTo($shiftEmail['address'])
                ->setBody("Echec de génération du code du boitier Igloohome\r\n - code http : " . $status . "\r\n - réponse : " . $content);
            $mailer->send($mail);
            return;
        }

        return $response->toArray()['code'];
    }

}
