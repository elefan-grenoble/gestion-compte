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
    var $issues;

    public function __construct(ShiftBucket $bucket)
    {
        $this->bucket = $bucket;
        $this->issues = array();
    }

    public function addIssue($issue)
    {
        $issues[] = $issue;
    }

    /**
     * @return mixed
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @return array
     */
    public function getIssues()
    {
        return $this->issues;
    }

}
