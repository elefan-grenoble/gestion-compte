<?php
// src/App/Validator/Constraints/BeneficiaryCanHostValidator.php
namespace App\Validator\Constraints;

use App\Entity\User;
use App\Service\MembershipService;
use App\Service\ShiftService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @Annotation
 */
class BeneficiaryCanHostValidator extends ConstraintValidator
{
    private $maximum_nb_of_beneficiaries_in_membership;
    private $container;

    /**
     * @var MembershipService
     */
    private $memberService;

    public function __construct(ContainerInterface $container, $maximum_nb_of_beneficiaries_in_membership)
    {
        $this->maximum_nb_of_beneficiaries_in_membership = $maximum_nb_of_beneficiaries_in_membership;
        $this->container = $container;
        $this->memberService = $container->get("membership_service");
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
        }else if (!$this->memberService->isUptodate($value->getMembership())) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Son compte est n\'est plus à jour d\'adhésion')
                ->addViolation();
        }else if ($value->getMembership()->getBeneficiaries()->count() >= $this->maximum_nb_of_beneficiaries_in_membership) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ host }}', '#'.$value->getMemberNumber().' de '.$value->getFirstname().' '.$value->getLastname())
                ->setParameter('{{ reason }}', 'Ce compte accueil déjà le nombre maximum de béneficiaires')
                ->addViolation();
        }
    }
}