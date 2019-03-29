<?php
// src/AppBundle/Validator/Constraints/BeneficiaryCanHostValidator.php
namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @Annotation
 */
class BeneficiaryCanHostValidator extends ConstraintValidator
{
    private $maximum_nb_of_beneficiaries_in_membership;

    public function __construct($maximum_nb_of_beneficiaries_in_membership)
    {
        $this->maximum_nb_of_beneficiaries_in_membership = $maximum_nb_of_beneficiaries_in_membership;
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
        }else if (!$value->getMembership()->isUptodate()) {
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