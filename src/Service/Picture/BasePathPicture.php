<?php

namespace App\Service\Picture;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class BasePathPicture
{
    protected $uploaderHelper;
    protected $imagineCacheManager;

    public function __construct(UploaderHelper $uploaderHelper, CacheManager $imagineCacheManager)
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineCacheManager = $imagineCacheManager;
    }

    /**
     * @param $entity
     * @param string $fileField
     * @param string $filter
     * @return string
     */
    public function getPicturePath($entity, string $fileField, string $filter)
    {
        $picturePath = $this->uploaderHelper->asset($entity, $fileField);

        return $this->imagineCacheManager->getBrowserPath($picturePath, $filter);
    }
}
