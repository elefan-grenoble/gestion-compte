<?php

namespace App\Form\DataTransformer;

use App\Entity\Beneficiary;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class BeneficiaryToStringTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
    * Transforms an object (Beneficiary) to a string.
    *
    * @param  Beneficiary|null $beneficiary
    * @return string
    */
    public function transform($beneficiary)
    {
        if ($beneficiary === null) {
            return '';
        }

        return $beneficiary->getDisplayNameWithMemberNumber();
    }

    /**
     * Transforms a string to an object (Beneficiary).
     *
     * @param  string $autocomplete
     * @return Beneficiary
     * @throws TransformationFailedException if object (Beneficiary) is not found
     */
    public function reverseTransform($autocomplete)
    {
        if (!$autocomplete) {
            return null;
        }

        $beneficiary = $this->entityManager
            ->getRepository(Beneficiary::class)
            ->findOneFromAutoComplete($autocomplete);

        if ($beneficiary === null) {
            throw new TransformationFailedException(sprintf(
                'Aucun utilisateur trouvé avec ces données "%s".',
                $autocomplete
            ));
        }

        return $beneficiary;
    }
}
