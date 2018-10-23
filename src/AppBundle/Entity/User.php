<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use DateTime;
use AppBundle\Repository\RegistrationRepository;
use Doctrine\ORM\EntityRepository;
use FOS\OAuthServerBundle\Model\ClientInterface;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @ORM\Table(name="fos_user")
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
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="registrar",cascade={"persist"})
     * @OrderBy({"date" = "DESC"})
     */
    private $recordedRegistrations;

    /**
     * Beneficiary's user.
     * @ORM\OneToOne(targetEntity="Beneficiary", mappedBy="user")
     */
    private $beneficiary;

    /**
     * Many Users have Many clients.
     * @ORM\ManyToMany(targetEntity="Client", inversedBy="users")
     * @ORM\JoinTable(name="users_clients")
     */
    private $clients;

    /**
     * @ORM\OneToMany(targetEntity="Note", mappedBy="author",cascade={"persist", "remove"})
     * @OrderBy({"created_at" = "DESC"})
     */
    private $annotations;

    public function getGroups()
    {
        if ($this->getBeneficiary()){
            return $this->getBeneficiary()->getFormations();
        }else{
            return new ArrayCollection();
        }

    }

    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        } elseif (property_exists($this->getBeneficiary(), $property)) {
            return $this->getBeneficiary()->$property;
        }
    }

    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }

    public function getFirstname() {
        $beneficiary = $this->getBeneficiary();
        if ($beneficiary)
            return $beneficiary->getFirstname();
        else
            return $this->getUsername();

    }

    public function getLastname() {
        $beneficiary = $this->getBeneficiary();
        if ($beneficiary)
            return $beneficiary->getLastname();
        else
            return '';
    }

    public function __toString()
    {
        return $this->getUsername();
    }

    public function getTmpToken($key = '')
    {
        return md5($this->getEmail() . $this->getLastname() . $this->getPassword() . $key . date('d'));
    }

    public function getAnonymousEmail()
    {
        $email = $this->getEmail();
        $splited = explode("@", $email);
        $return = '';
        foreach ($splited as $part) {
            $splited_part = explode(".", $part);
            foreach ($splited_part as $mini_part) {
                $first_char = substr($mini_part, 0, 1);
                $last_char = substr($mini_part, strlen($mini_part) - 1, 1);
                $center = substr($mini_part, 1, strlen($mini_part) - 2);
                if (strlen($center) > 0)
                    $return .= $first_char . preg_replace('/./', '_', $center) . $last_char;
                elseif (strlen($mini_part) > 1)
                    $return .= $first_char . $last_char;
                else
                    $return .= $first_char;
                $return .= '.';
            }
            $return = substr($return, 0, strlen($return) - 1);
            $return .= '@';
        }
        $return = substr($return, 0, strlen($return) - 1);
        return preg_replace('/_{3}_*/', '___', $return);
    }

    public function getAnonymousLastname()
    {
        $lastname = $this->getLastname();
        $splited = explode(" ", $lastname);
        $return = '';
        foreach ($splited as $part) {
            $splited_part = explode("-", $part);
            foreach ($splited_part as $mini_part) {
                $first_char = substr($mini_part, 0, 1);
                $last_char = substr($mini_part, strlen($mini_part) - 1, 1);
                $center = substr($mini_part, 1, strlen($mini_part) - 2);
                if (strlen($center) > 0)
                    $return .= $first_char . preg_replace('/./', '*', $center) . $last_char;
                else
                    $return .= $first_char . $last_char;
                $return .= '-';
            }
            $return = substr($return, 0, strlen($return) - 1);
            $return .= ' ';
        }
        $return = substr($return, 0, strlen($return) - 1);
        return $return;
    }

    static function makeUsername($firstname, $lastname, $extra = '')
    {
        $lastname = preg_replace('/[-\/]+/', ' ', $lastname);
        $ln = explode(' ', $lastname);
//        if (in_array(strtolower($ln[0]),array('la','du','de'))&&count($ln>1))
        if (strlen($ln[0]) < 3 && count($ln) > 1)
            $ln = $ln[0] . $ln[1];
        else
            $ln = $ln[0];
        $username = strtolower(substr(explode(' ', $firstname)[0], 0, 1) . $ln);
        $username = preg_replace('/[^a-z0-9]/', '', $username);
        $username .= $extra;
        return $username;
    }

    static function randomPassword()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    /**
     * Add recordedRegistration
     *
     * @param \AppBundle\Entity\Registration $recordedRegistration
     *
     * @return User
     */
    public function addRecordedRegistration(\AppBundle\Entity\Registration $recordedRegistration)
    {
        $this->recordedRegistrations[] = $recordedRegistration;

        return $this;
    }

    /**
     * Remove recordedRegistration
     *
     * @param \AppBundle\Entity\Registration $recordedRegistration
     */
    public function removeRecordedRegistration(\AppBundle\Entity\Registration $recordedRegistration)
    {
        $this->recordedRegistrations->removeElement($recordedRegistration);
    }

    /**
     * Get recordedRegistrations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRecordedRegistrations()
    {
        return $this->recordedRegistrations;
    }

    /**
     * determine whether the given client (ClientInterface) is allowed by the user, or not.
     * @param ClientInterface $client
     * @return bool
     */
    public function isAuthorizedClient(Client $client)
    {
        return $this->getClients()->contains($client);
    }

    /**
     * Add client
     *
     * @param \AppBundle\Entity\Client $client
     *
     * @return User
     */
    public function addClient(\AppBundle\Entity\Client $client)
    {
        $this->clients[] = $client;

        return $this;
    }

    /**
     * Remove client
     *
     * @param \AppBundle\Entity\Client $client
     */
    public function removeClient(\AppBundle\Entity\Client $client)
    {
        $this->clients->removeElement($client);
    }

    /**
     * Get clients
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getClients()
    {
        return $this->clients;
    }


    /**
     * Add annotation
     *
     * @param \AppBundle\Entity\Note $annotation
     *
     * @return User
     */
    public function addAnnotation(\AppBundle\Entity\Note $annotation)
    {
        $this->annotations[] = $annotation;

        return $this;
    }

    /**
     * Remove annotation
     *
     * @param \AppBundle\Entity\Note $annotation
     */
    public function removeAnnotation(\AppBundle\Entity\Note $annotation)
    {
        $this->annotations->removeElement($annotation);
    }

    /**
     * Get annotations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    public function getAutocompleteLabel(){
        if ($this->getBeneficiary())
            return '#'.$this->getId().' '.$this->getFirstname().' '.$this->getLastname();
        else
            return '#'.$this->getId().' '.$this->getUsername();
    }

    /**
     * @return Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @param mixed $beneficiary
     */
    public function setBeneficiary($beneficiary)
    {
        $this->beneficiary = $beneficiary;
    }
}
