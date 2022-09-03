<?php
// src/AppBundle/Validator/Constraints/BeneficiaryCanHost.php
namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class BeneficiaryCanHost extends Constraint
{
    public $message = 'Le compte {{ host }} ne peux pas héberger un nouveau bénéficiaire. {{ reason }}';
}