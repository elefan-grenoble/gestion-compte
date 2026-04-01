<?php

declare(strict_types=1);

namespace App\Providers;

use League\OAuth2\Client\Token\AccessTokenInterface;

interface OauthAuthenticatorInterface
{
    public function getToken(string $authUrl, string $clientId, string $clientSecret): AccessTokenInterface;
}
