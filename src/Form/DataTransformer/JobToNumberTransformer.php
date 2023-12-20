<?php

namespace App\Form\DataTransformer;

use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class JobToNumberTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
    * Transforms an object (job) to a string (id).
    *
    * @param  Job|null $job
    * @return string
    */
    public function transform($job)
    {
        if (null === $job) {
            return '';
        }

        return $job->getId();
    }

    /**
     * Transforms a string (id) to an object (job).
     *
     * @param  string $jobId
     * @return Job
     * @throws TransformationFailedException if object (job) is not found
     */
    public function reverseTransform($jobId)
    {
        if (!$jobId) {
            return;
        }

        $job = $this->entityManager
                    ->getRepository(Job::class)
                    ->find($jobId)
        ;

        if (null === $job) {
            throw new TransformationFailedException(sprintf(
                'Aucune formation ne correspond à l\'id "%s" !',
                $jobId
            ));
        }

        return $job;
    }
}
