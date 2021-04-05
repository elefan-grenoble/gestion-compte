<?php
/**
 * Created by PhpStorm.
 * User: gjanssens
 * Date: 03/03/19
 * Time: 09:17
 */

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

        return $beneficiary->getAutocompleteLabelFull();
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
            return;
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        $beneficiaryRepo = $this->entityManager->getRepository(Beneficiary::class);

        $re = '/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+).*\(([0-9]+)\)/';
        preg_match($re, $autocomplete, $matches, PREG_OFFSET_CAPTURE, 0);

        if (count($matches)<=1){
            throw new TransformationFailedException(sprintf(
                'Aucun utilisateur trouvé avec ces données "%s".',
                $autocomplete
            ));
        }

        $user = $userRepo->findOneBy(array('email'=>$matches[1][0]));
        $beneficiary = $beneficiaryRepo->find($matches[2][0]);

        if (null === $user) {
            throw new TransformationFailedException(sprintf(
                'Aucun utilisateur trouvé avec cet email "%s" !',
                $matches[1][0]
            ));
        }

        if (null === $beneficiary) {
            throw new TransformationFailedException(sprintf(
                'Aucun beneficiaire trouvé avec cet id "%s" !',
                $matches[2][0]
            ));
        }

        if ($user != $beneficiary->getUser()) {
            throw new TransformationFailedException('L\'email et l\'identifiant ne correspondent pas'
            );
        }

        return $beneficiary;
    }
}
