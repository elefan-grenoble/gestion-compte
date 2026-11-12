<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * The name of an uploaded image ends with an image extension. The uploader
 * keeps the extension the client sent, so an image with a .php name would
 * be stored as such in the public uploads directory (C-SEC-3).
 *
 * @Annotation
 */
class ImageExtension extends Constraint
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** @var string */
    public $message = 'Le fichier doit avoir l\'extension d\'une image ({{ extensions }}).';
}
