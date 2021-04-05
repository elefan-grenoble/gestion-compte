<?php
// src/App/Validator/Constraints/BeneficiaryCanHost.php
namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class BeneficiaryCanHost extends Constraint
{
    public $message = 'Le compte {{ host }} ne peux pas heberger un nouveau beneficiaire. {{ reason }}';
}