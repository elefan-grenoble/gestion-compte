<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="setting")
 *
 * @ORM\Entity(repositoryClass="App\Repository\SettingRepository")
 */
class Setting
{
    public const DEFAULTS = [
        'main_color' => '#51CAE9'
    ];

    /**
     * @ORM\Column(name="setting_key", type="string")
     * @ORM\Id
     */
    private string $key;

    /**
     * @ORM\Column(name="value", type="string", nullable=true)
     */
    private ?string $value;

    public function __construct(
        string $key,
        ?string $value
    ) {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): Setting
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): Setting
    {
        $this->value = $value;

        return $this;
    }
}
