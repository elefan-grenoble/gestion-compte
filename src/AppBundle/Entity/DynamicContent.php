<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Dynamic Content (CMS)
 *
 * @ORM\Table(name="dynamic_content")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DynamicContentRepository")
 */
class DynamicContent
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=64)
     */
    protected $code;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=64, options={"default" : "general"})
     *
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=64)
     *
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     *
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text")
     */
    protected $content;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

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
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return DynamicContent
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType(): string
    {
        if ($this->type == 'general') {
            return 'Général';
        }
        return $this->type;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return DynamicContent
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function __toString()
    {
        return $this->getName();
    }

}
