<?php

declare(strict_types=1);

namespace App\Providers;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CacheOauthAuthenticatorDecorator implements OauthAuthenticatorInterface
{
    public const CACHE_DEFAULT_TTL = 600;

    private OauthAuthenticatorInterface $authenticator;
    private CacheInterface $cache;

    public function __construct(OauthAuthenticatorInterface $authenticator)
    {
        $this->authenticator = $authenticator;
        $this->cache = new FilesystemAdapter();
    }

    public function getToken(string $authUrl, string $clientId, string $clientSecret): AccessTokenInterface
    {
        return $this->cache->get($clientId, function (ItemInterface $item) use ($authUrl, $clientId, $clientSecret) {
            $item->expiresAfter(self::CACHE_DEFAULT_TTL);
            $token = $this->authenticator->getToken($authUrl, $clientId, $clientSecret);
            $expires = $token->getExpires();
            if (is_int($expires)) {
                $item->expiresAfter($expires - time());
            }

            return $token;
        });
    }
}
