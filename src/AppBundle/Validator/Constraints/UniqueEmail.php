<?php
// src/AppBundle/Validator/Constraints/UniqueEmail.php
namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueEmail extends Constraint
{
    public $message = 'Cet email est déjà utilisé.';
}
