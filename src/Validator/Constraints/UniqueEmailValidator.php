<?php
// src/App/Validator/Constraints/UniqueEmailValidator.php
namespace App\Validator\Constraints;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueEmailValidator extends ConstraintValidator
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function getEntityManager(){
        return $this->em;
    }

    public function validate($value, Constraint $constraint)
    {
        // check if email already used
        $exist = $this->getEntityManager()->getRepository(User::class)->findOneBy(array('email'=>$value));
        if ($exist) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }

    }
}