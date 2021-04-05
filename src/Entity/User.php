<?php
// src/App/Entity/User.php

namespace App\Entity;

use DateTime;
use App\Repository\RegistrationRepository;
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
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(name="fos_user")
 * @UniqueEntity(fields={"email"}, message="Cette adresse e-mail est déjà utilisée par un autre compte")
 * @UniqueEntity(fields={"username"}, message="Ce nom d'utilisateur est déjà utilisé par un autre compte")
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
     * @Assert\Valid()
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

    /**
     * @ORM\OneToMany(targetEntity="ProcessUpdate", mappedBy="author",cascade={"persist"})
     * @OrderBy({"date" = "DESC"})
     */
    private $processUpdates;

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
        if (!$this->getBeneficiary())
            return $this->getUsername();
        else{
            return (string)$this->getBeneficiary();
        }
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
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

        $firstname = strtr( $firstname, $unwanted_array );
        $lastname = strtr( $lastname, $unwanted_array );

        $lastname = preg_replace('/[-\/]+/', ' ', $lastname);
        $ln = explode(' ', $lastname);

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
        return \bin2hex(\random_bytes(20));
    }

    /**
     * Add recordedRegistration
     *
     * @param \App\Entity\Registration $recordedRegistration
     *
     * @return User
     */
    public function addRecordedRegistration(\App\Entity\Registration $recordedRegistration)
    {
        $this->recordedRegistrations[] = $recordedRegistration;

        return $this;
    }

    /**
     * Remove recordedRegistration
     *
     * @param \App\Entity\Registration $recordedRegistration
     */
    public function removeRecordedRegistration(\App\Entity\Registration $recordedRegistration)
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
     * @param \App\Entity\Client $client
     *
     * @return User
     */
    public function addClient(\App\Entity\Client $client)
    {
        $this->clients[] = $client;

        return $this;
    }

    /**
     * Remove client
     *
     * @param \App\Entity\Client $client
     */
    public function removeClient(\App\Entity\Client $client)
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
     * @param \App\Entity\Note $annotation
     *
     * @return User
     */
    public function addAnnotation(\App\Entity\Note $annotation)
    {
        $this->annotations[] = $annotation;

        return $this;
    }

    /**
     * Remove annotation
     *
     * @param \App\Entity\Note $annotation
     */
    public function removeAnnotation(\App\Entity\Note $annotation)
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

    /**
     * @return mixed
     */
    public function getProcessUpdates()
    {
        return $this->processUpdates;
    }
}
