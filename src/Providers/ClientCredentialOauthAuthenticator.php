<?php

declare(strict_types=1);

namespace App\Providers;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Client\ClientExceptionInterface;

class ClientCredentialOauthAuthenticator implements OauthAuthenticatorInterface
{
    /**
     * @throws ClientExceptionInterface
     * @throws IdentityProviderException
     */
    public function getToken(string $authUrl, string $clientId, string $clientSecret): AccessTokenInterface
    {
        $provider = new GenericProvider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'urlAccessToken' => $authUrl,
            'urlAuthorize' => '',
            'urlResourceOwnerDetails' => ''
        ]);

        return $provider->getAccessToken('client_credentials');
    }
}