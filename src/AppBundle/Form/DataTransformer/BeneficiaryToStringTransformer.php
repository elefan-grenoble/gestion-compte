<?php
/**
 * Created by PhpStorm.
 * User: gjanssens
 * Date: 03/03/19
 * Time: 09:17
 */

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\User;
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
    * Transforms an object (Membership) to a string (email).
    *
    * @param  Beneficiary|null $beneficiary
    * @return string
    */
    public function transform($beneficiary)
    {
        if (null === $beneficiary) {
            return '';
        }

        return $beneficiary->getDisplayNameWithMemberNumber();
    }

    /**
     * Transforms a string (email) to an object (Beneficiary).
     *
     * @param  string $autocomplete
     * @return Beneficiary
     * @throws TransformationFailedException if object (user) is not found
     */
    public function reverseTransform($autocomplete)
    {
        if (!$autocomplete) {
            return null;
        }

        $beneficiary = $this->entityManager
                            ->getRepository(Beneficiary::class)
                            ->findOneFromAutoComplete($autocomplete);

        if (null === $beneficiary){
            throw new TransformationFailedException(sprintf(
                'Aucun utilisateur trouvé avec ces données "%s".',
                $autocomplete
            ));
        }

        return $beneficiary;
    }
}
