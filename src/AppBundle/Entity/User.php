<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 * @UniqueEntity("member_number")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     * @Assert\NotBlank(message="Merci d'entrer votre numéro d'adhérent.", groups={"Registration"})
     */
    protected $member_number;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $lastname;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $firstname;

    /**
     * @ORM\Column(type="date", nullable=false, options={"default": "2017-01-01"})
     */
    protected $registration_birthday;

    /**
     * @ORM\Column(type="string")
     */
    protected $phone;



    public function __construct()
    {
        $this->registration_birthday = new \Date('2017-01-01');
        $this->phone = '';
        parent::__construct();
        // your own logic
    }


    /**
     * Set memberNumber
     *
     * @param integer $memberNumber
     *
     * @return User
     */
    public function setMemberNumber($memberNumber)
    {
        $this->member_number = $memberNumber;

        return $this;
    }

    /**
     * Get memberNumber
     *
     * @return integer
     */
    public function getMemberNumber()
    {
        return $this->member_number;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     *
     * @return User
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set firstname
     *
     * @param string $firstname
     *
     * @return User
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set registrationBirthday
     *
     * @param \DateTime $registrationBirthday
     *
     * @return User
     */
    public function setRegistrationBirthday($registrationBirthday)
    {
        $this->registration_birthday = $registrationBirthday;

        return $this;
    }

    /**
     * Get registrationBirthday
     *
     * @return \DateTime
     */
    public function getRegistrationBirthday()
    {
        return $this->registration_birthday;
    }

    /**
     * Set phone
     *
     * @param string $phone
     *
     * @return User
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }
}
