<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class SettingsManager
{
    private SettingRepository $repo;
    private CacheInterface $cache;
    private EntityManagerInterface $em;

    public function __construct(
        CacheInterface $cache,
        EntityManagerInterface $em
    ) {
        $this->em = $em;
        $this->cache = $cache;
        $this->repo = $em->getRepository(Setting::class);
    }

    public function get(string $key, ?string $default = null)
    {
        return $this->all()[$key] ?? $default ?? Setting::DEFAULTS[$key];
    }

    public function set(string $key, string $value): void
    {
        $setting = $this->repo->findOneBy(['key' => $key]) ?? new Setting($key, $value);
        $setting->setValue($value);
        $this->em->persist($setting);
        $this->em->flush();
        $this->cache->delete('settings.all');
    }

    /**
     * @param array<string, mixed> $settings
     * @return void
     */
    public function setAll(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, json_encode($value));
        }
    }

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return $this->cache->get('settings.all', fn() => $this->repo->findAllAsKeyValue());
    }
}
