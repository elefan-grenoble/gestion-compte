<?php

namespace AppBundle\Serializer;

class CircularReferenceHandler {
    public function __invoke($object) {
        return $object->getId();
    }
}
