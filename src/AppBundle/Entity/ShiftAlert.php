<?php

namespace AppBundle\Entity;

use AppBundle\Entity\ShiftBucket;

/**
 * Shift Alert
 *
 */
class ShiftAlert
{

    var $bucket;
    var $issue;

    public function __construct(ShiftBucket $bucket, string $issue)
    {
        $this->bucket = $bucket;
        $this->issue = $issue;
    }

    /**
     * @return mixed
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @return string
     */
    public function getIssue()
    {
        return $this->issue;
    }

}
