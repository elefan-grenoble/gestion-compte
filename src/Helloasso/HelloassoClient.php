<?php

declare(strict_types=1);

namespace App\Helloasso;

use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class HelloassoClient
{
    /** @var ContainerInterface */
    private $container;

    private $cache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = new FilesystemAdapter();
    }

    private function getToken(): AccessTokenInterface
    {
        $item = $this->cache->getItem('helloasso_token');
        if (!$item->isHit() || !$item->get() instanceof AccessTokenInterface || $item->get()->hasExpired()) {
            return $this->refreshToken($item);
        }

        return $item->get();
    }

    private function refreshToken(CacheItemInterface $item): AccessTokenInterface
    {
        $provider = new GenericProvider([
            'clientId' => $this->container->getParameter('helloasso_client_id'),
            'clientSecret' => $this->container->getParameter('helloasso_client_secret'),
            'urlAccessToken' => $this->container->getParameter('helloasso_api_auth_url'),
            'urlAuthorize' => '',
            'urlResourceOwnerDetails' => ''
        ]);

        $token = $provider->getAccessToken('client_credentials');

        $item->set($token);
        $this->cache->save($item);

        return $token;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => $this->container->getParameter('helloasso_api_base_url'),
            'headers' => ['Authorization' => 'Bearer '.$this->getToken()->getToken()],
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
                $this->container->getParameter('helloasso_organization_slug'),
            ),
        );

        return json_decode((string)$result->getBody())->data;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getFormPayments(string $formType, string $formSlug, array $params): \stdClass
    {
        $result = $this->getClient()->get(
            sprintf(
                'organizations/%s/forms/%s/%s/payments',
                $this->container->getParameter('helloasso_organization_slug'),
                $formType,
                $formSlug,
            ),
            [
                'query' => array_merge($params, ['states' => ['Authorized']]),
            ],
        );

        return json_decode((string)$result->getBody());
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function getFormDetails(string $formType, string $formSlug): \stdClass
    {
        $result = $this->getClient()->get(
            sprintf(
                'organizations/%s/forms/%s/%s/public',
                $this->container->getParameter('helloasso_organization_slug'),
                $formType,
                $formSlug,
            ),
        );

        return json_decode((string)$result->getBody());
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

        return json_decode((string)$result->getBody());
    }
}
