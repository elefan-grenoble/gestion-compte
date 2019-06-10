<?php

namespace AppBundle\Controller\Rest;

use AppBundle\Entity\User;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 */
class ConfigController extends AbstractFOSRestController
{

    /**
     * @Rest\Get("/config")
     */
    public function getConfigAction()
    {
        $response = [
            'siteName' => $this->container->get('site_name')
//            'projectName' => $this->container->getParameter('project_name'),
//            'projectUrl' => $this->container->getParameter('project_url'),
//            'projectUrlDisplay' => $this->container->getParameter('project_url_display'),
//            'mainColor' => $this->container->getParameter('main_color')
        ];

        return $this->json($response);
    }

}
