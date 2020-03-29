<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as AppAssert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * AnonymousBeneficiary
 *
 * @ORM\Table(name="anonymous_beneficiary")
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity("email")
 * @ORM\Entity(repositoryClass="App\Repository\AnonymousBeneficiaryRepository")
 */
class AnonymousBeneficiary
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=191, unique=true)
     * @Assert\Email(strict="true")
     * @Assert\NotBlank(message="L'email doit être saisie")
     * @AppAssert\UniqueEmail
     */
    private $email;

    /**
     * @ORM\OneToOne(targetEntity="Beneficiary")
     * @ORM\JoinColumn(name="join_to", referencedColumnName="id", onDelete="SET NULL")
     * @AppAssert\BeneficiaryCanHost
     */
    private $join_to;

    /**
     * @var string
     *
     * @ORM\Column(name="beneficiaries_emails", type="string", length=255, nullable=true)
     */
    private $beneficiaries_emails;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="string", length=255, nullable=true)
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="mode", type="integer", nullable=true)
     */
    private $mode;


    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="registrar_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registrar;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $created_at;


    /**
     * @var \DateTime
     *
     * @ORM\Column(name="recall_date", type="datetime", nullable=true)
     */
    private $recall_date;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set amount
     *
     * @param string $amount
     *
     * @return AnonymousBeneficiary
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set beneficiaries_emails
     *
     * @param string $beneficiaries_emails
     *
     * @return AnonymousBeneficiary
     */
    public function setBeneficiariesEmails($beneficiaries_emails)
    {
        $this->beneficiaries_emails = $beneficiaries_emails;

        return $this;
    }

    /**
     * Get beneficiaries_emails
     *
     * @return string
     */
    public function getBeneficiariesEmails()
    {
        return $this->beneficiaries_emails;
    }

    /**
     * Get beneficiaries_emails as array
     *
     * @return array
     */
    public function getBeneficiariesEmailsAsArray()
    {
        return array_filter(explode(', ',$this->getBeneficiariesEmails()));
    }

    /**
     * Set mode
     *
     * @param string $mode
     *
     * @return Registration
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Get mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->created_at = new \DateTime();
    }

    /**
     * Get created_at
     *
     * @return \DateTime
     */
    public function getCreatedAt(){
        return $this->created_at;
    }

    /**
     * Set recall_date
     *
     * @param \DateTime $date
     *
     * @return AnonymousBeneficiary
     */
    public function setRecallDate(\DateTime $date)
    {
        $this->recall_date = $date;

        return $this;
    }

    /**
     * Get recall_date
     *
     * @return \DateTime
     */
    public function getRecallDate(){
        return $this->recall_date;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return AnonymousBeneficiary
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }


    /**
     * Set join_to
     *
     * @param Beneficiary $beneficiary
     *
     * @return AnonymousBeneficiary
     */
    public function setJoinTo($beneficiary)
    {
        $this->join_to = $beneficiary;

        return $this;
    }

    /**
     * Get join_to_email
     *
     * @return Beneficiary
     */
    public function getJoinTo()
    {
        return $this->join_to;
    }

    /**
     * Set registrar
     *
     * @param \App\Entity\User $registrar
     *
     * @return AnonymousBeneficiary
     */
    public function setRegistrar(\App\Entity\User $registrar = null)
    {
        $this->registrar = $registrar;

        return $this;
    }

    /**
     * Get registrar
     *
     * @return \App\Entity\User
     */
    public function getRegistrar()
    {
        return $this->registrar;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        // check data consistency
        if (!$this->getJoinTo()){
            if (!$this->getAmount()&&$this->getMode()!=Registration::TYPE_HELLOASSO) {
                $context->buildViolation('Pour une nouvelle adhésion, merci de saisir un montant')
                    ->atPath('amount')
                    ->addViolation();
            }
            if (!$this->getMode()) {
                $context->buildViolation('Merci de saisir le moyen de paiement')
                    ->atPath('mode')
                    ->addViolation();
            }
        }else{
            if ($this->getAmount()) {
                $context->buildViolation('Pour un ajout de beneficiaire sur un compte existant, merci de ne pas enregistrer de paiement')
                    ->atPath('amount')
                    ->addViolation();
            }
            if ($this->getMode()) {
                $context->buildViolation('Pour un ajout de beneficiaire sur un compte existant, merci de ne pas enregistrer de mode paiement')
                    ->atPath('mode')
                    ->addViolation();
            }

        }

    }
}
