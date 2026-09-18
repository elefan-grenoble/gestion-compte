<?php

declare(strict_types=1);

namespace App\Providers\Igloohome;

use App\Providers\OauthAuthenticatorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;

class IgloohomeClient
{
    private OauthAuthenticatorInterface $authenticator;
    private $igloohomeClientId; // @phpstan-ignore missingType.property
    private $igloohomeClientSecret; // @phpstan-ignore missingType.property
    private $igloohomeApiAuthUrl; // @phpstan-ignore missingType.property
    private $igloohomeApiDevice; // @phpstan-ignore missingType.property

    public function __construct(// @phpstan-ignore-line
        OauthAuthenticatorInterface $authenticator,
        $igloohomeClientId,
        $igloohomeClientSecret,
        $igloohomeApiAuthUrl,
        $igloohomeApiDevice
    ) {
        $this->igloohomeApiDevice = $igloohomeApiDevice;
        $this->igloohomeApiAuthUrl = $igloohomeApiAuthUrl;
        $this->igloohomeClientSecret = $igloohomeClientSecret;
        $this->igloohomeClientId = $igloohomeClientId;
        $this->authenticator = $authenticator;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => $this->igloohomeApiDevice,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authenticator->getToken(
                    $this->igloohomeApiAuthUrl,
                    $this->igloohomeClientId,
                    $this->igloohomeClientSecret,
                ),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, application/xml',
            ],
        ]);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function regenerateCode(string $start, string $end): string
    {
        $result = $this->getClient()->post('algopin/hourly', [
            RequestOptions::JSON => [
                'variance' => 1,
                'startDate' => $start,
                'endDate' => $end,
                'accessName' => $start,
            ],
        ]);

        return $result->getBody()->getContents();
    }
}
