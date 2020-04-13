<?php
// src/App/Validator/Constraints/BeneficiaryCanHostValidator.php
namespace App\Validator\Constraints;

use App\Service\MembershipService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @Annotation
 */
class BeneficiaryCanHostValidator extends ConstraintValidator
{
    /**
     * @var MembershipService
     */
    private $membershipService;
    private $maximumNbOfBeneficiariesInMembership;

    public function __construct(MembershipService $membershipService, $maximumNbOfBeneficiariesInMembership)
    {
        $this->membershipService = $membershipService;
        $this->maximumNbOfBeneficiariesInMembership = $maximumNbOfBeneficiariesInMembership;
    }

    public function validate($value, Constraint $constraint)
    {
        if ($value === null){
            return;
        }
        if (!$value->getMembership()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Cet utilisateur n\'a pas de carte membre')
                ->addViolation();
        }else if ($value->getMembership()->isWithdrawn()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Son compte est fermé')
                ->addViolation();
        }else if (!$this->membershipService->isUptodate($value->getMembership())) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Son compte est n\'est plus à jour d\'adhésion')
                ->addViolation();
        }else if ($value->getMembership()->getBeneficiaries()->count() >= $this->maximumNbOfBeneficiariesInMembership) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Ce compte accueil déjà le nombre maximum de béneficiaires')
                ->addViolation();
        }
    }
}