<?php
// src/App/Entity/Client.php

namespace App\Entity;

use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Client extends BaseClient
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Service", inversedBy="clients")
     * @ORM\JoinColumn(name="service_id", referencedColumnName="id")
     */
    private $service;

    /**
     * Many Clients have Many Users.
     * @ORM\ManyToMany(targetEntity="User", mappedBy="clients")
     */
    private $users;

    public function __construct()
    {
        parent::__construct();
        // your own logic
    }

    /**
     *
     * @return String
     */
    public function getUrls(){
        return implode(',',$this->getRedirectUris());

    }

    /**
     * Set service
     *
     * @param \App\Entity\Service $service
     *
     * @return Client
     */
    public function setService(\App\Entity\Service $service = null)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Get service
     *
     * @return \App\Entity\Service
     */
    public function getService()
    {
        return $this->service;
    }


    /**
     * Add user
     *
     * @param \App\Entity\User $user
     *
     * @return Client
     */
    public function addUser(\App\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \App\Entity\User $user
     */
    public function removeUser(\App\Entity\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }
}
