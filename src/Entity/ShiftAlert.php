<?php

namespace App\Entity;

/**
 * Shift Alert.
 */
class ShiftAlert
{
    public $bucket;
    public $issue;

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
