<?php

declare(strict_types=1);

namespace App\Providers\Helloasso;

use App\Providers\OauthAuthenticatorInterface;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientExceptionInterface;

class HelloassoClient
{
    private OauthAuthenticatorInterface $authenticator;
    private string $helloAssoClientId;
    private string $helloAssoClientSecret;
    private string $helloAssoApiAuthUrl;
    private string $helloAssoApiBaseUrl;
    private string $helloAssoOrganizationSlug;

    public function __construct(
        OauthAuthenticatorInterface $authenticator,
        string $helloAssoClientId,
        string $helloAssoClientSecret,
        string $helloAssoApiAuthUrl,
        string $helloAssoApiBaseUrl,
        string $helloAssoOrganizationSlug
    ) {
        $this->helloAssoOrganizationSlug = $helloAssoOrganizationSlug;
        $this->helloAssoApiBaseUrl = $helloAssoApiBaseUrl;
        $this->helloAssoApiAuthUrl = $helloAssoApiAuthUrl;
        $this->helloAssoClientSecret = $helloAssoClientSecret;
        $this->helloAssoClientId = $helloAssoClientId;
        $this->authenticator = $authenticator;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => $this->helloAssoApiBaseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authenticator->getToken(
                    $this->helloAssoApiAuthUrl,
                    $this->helloAssoClientId,
                    $this->helloAssoClientSecret,
                ),
            ],
        ]);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getForms(): array
    {
        $result = $this->getClient()->get(
            sprintf(
                'organizations/%s/forms',
                $this->helloAssoOrganizationSlug,
            ),
        );

        return json_decode((string) $result->getBody())->data;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getFormPayments(string $formType, string $formSlug, array $params): \stdClass
    {
        $result = $this->getClient()->get(
            sprintf(
                'organizations/%s/forms/%s/%s/payments',
                $this->helloAssoOrganizationSlug,
                $formType,
                $formSlug,
            ),
            [
                'query' => array_merge($params, ['states' => ['Authorized']]),
            ],
        );

        return json_decode((string) $result->getBody());
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getFormDetails(string $formType, string $formSlug): \stdClass
    {
        $result = $this->getClient()->get(
            sprintf(
                'organizations/%s/forms/%s/%s/public',
                $this->helloAssoOrganizationSlug,
                $formType,
                $formSlug,
            ),
        );

        return json_decode((string) $result->getBody());
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getPayment(string $paymentId): \stdClass
    {
        $result = $this->getClient()->get(
            sprintf(
                'payments/%s',
                $paymentId,
            ),
        );

        return json_decode((string) $result->getBody());
    }
}
