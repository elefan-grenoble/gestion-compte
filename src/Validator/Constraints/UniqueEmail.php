<?php
// src/App/Validator/Constraints/UniqueEmail.php
namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueEmail extends Constraint
{
    public $message = 'Cet email est déjà utilisé.';
}
