<?php

namespace App\Service\Picture;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class BasePathPicture
{

    /** @var array */
    protected $placeholders;

    /** @var CacheManager */
    protected $imagineCacheManager;

    public function __construct(UploaderHelper $uploaderHelper, CacheManager $imagineCacheManager)
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineCacheManager = $imagineCacheManager;
    }

    /**
     * @param $entity
     * @param string $filter
     * @param string $fileField
     * @return string
     */
    public function getPicturePath($entity, string $fileField, string $filter)
    {
        $picturePath = $this->uploaderHelper->asset($entity, $fileField);

        return $this->imagineCacheManager->getBrowserPath($picturePath, $filter);
    }

}
