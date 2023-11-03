<?php

namespace App\Form\DataTransformer;

use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class MembershipToStringTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
    * Transforms an object (Membership) to a string.
    *
    * @param  Membership|null $membership
    * @return string
    */
    public function transform($membership)
    {
        if (null === $membership) {
            return '';
        }

        return $membership->getDisplayNameWithMemberNumber();
    }

    /**
     * Transforms a string to an object (Membership).
     *
     * @param  string $autocomplete
     * @return Membership
     * @throws TransformationFailedException if object (Membership) is not found
     */
    public function reverseTransform($autocomplete)
    {
        if (!$autocomplete) {
            return null;
        }

        $membership = $this->entityManager
            ->getRepository(Membership::class)
            ->findOneFromAutoComplete($autocomplete);

        if ($membership === null) {
            throw new TransformationFailedException(sprintf(
                'Aucun utilisateur trouvé avec ces données "%s".',
                $autocomplete
            ));
        }

        return $membership;
    }
}
