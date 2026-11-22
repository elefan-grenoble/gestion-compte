<?php

namespace App\Twig\Extension;

use App\Service\SettingsManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingExtension extends AbstractExtension
{
    private SettingsManager $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [$this, 'setting']),
        ];
    }

    public function setting(string $key, $default = null): ?string
    {
        return $this->settingsManager->get($key, $default);
    }
}
