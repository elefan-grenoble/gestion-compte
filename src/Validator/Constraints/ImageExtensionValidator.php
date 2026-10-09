<?php

namespace App\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ImageExtensionValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof File) {
            return;
        }

        $name = $value instanceof UploadedFile ? $value->getClientOriginalName() : $value->getFilename();
        if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ImageExtension::EXTENSIONS, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ extensions }}', implode(', ', ImageExtension::EXTENSIONS))
                ->addViolation()
            ;
        }
    }
}
