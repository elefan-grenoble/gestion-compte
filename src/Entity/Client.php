<?php

// src/App/Entity/Client.php

namespace App\Entity;

use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity
 */
class Client extends BaseClient
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Service", inversedBy="clients")
     *
     * @ORM\JoinColumn(name="service_id", referencedColumnName="id")
     */
    private $service;

    /**
     * Many Clients have Many Users.
     *
     * @ORM\ManyToMany(targetEntity="User", mappedBy="clients")
     */
    private $users;

    public function __construct()
    {
        parent::__construct();
        // your own logic
    }

    /**
     * @return string
     */
    public function getUrls()
    {
        return implode(',', $this->getRedirectUris());

    }

    /**
     * Set service.
     *
     * @return Client
     */
    public function setService(?Service $service = null)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Get service.
     *
     * @return Service
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Add user.
     *
     * @return Client
     */
    public function addUser(User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user.
     */
    public function removeUser(User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return Collection
     */
    public function getUsers()
    {
        return $this->users;
    }
}
