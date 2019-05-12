<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Job;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\Controller\AbstractFOSRestController;

/**
 * @Rest\Route("/api/test")
 */
class TestAngularController extends AbstractFOSRestController
{
    /**
     * @Rest\Get("")
     * @Rest\View(statusCode = 200)
     *
     */
    public function getJobs()
    {
        $em = $this->getDoctrine();
        $jobs = $em->getRepository(Job::class)->findAll();
        return $this->view($jobs);
    }

}