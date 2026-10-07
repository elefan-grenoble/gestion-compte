<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Security\KnownOpenVulnerability;

/**
 * I-SEC-7 (SEC.1-4, SPEC.8): POST /helloassoNotify, the HelloAsso webhook,
 * authenticates nothing. Every notification of a validated payment makes
 * the application call the HelloAsso API, with data['id'] pasted into the
 * API path.
 *
 * Each test runs as a configured instance would (dummy API credentials),
 * with the HelloAsso endpoints pointing to a closed local port: no request
 * leaves the machine whatever the outcome.
 *
 * @internal
 */
class HelloassoNotifySecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    private const ENVIRONMENT = [
        'HELLOASSO_CLIENT_ID' => 'test-client-id',
        'HELLOASSO_CLIENT_SECRET' => 'test-client-secret',
        'HELLOASSO_API_AUTH_URL' => 'http://127.0.0.1:9/oauth2/token',
        'HELLOASSO_API_BASE_URL' => 'http://127.0.0.1:9/v5/',
    ];

    /** @var array<string, array{mixed, mixed}> */
    private $savedEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::ENVIRONMENT as $name => $value) {
            $this->savedEnvironment[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnvironment as $name => [$env, $server]) {
            if (null === $env) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        parent::tearDown();
    }

    /**
     * The webhook stays reachable: HelloAsso cannot log in.
     */
    public function testANotificationOfAnUnvalidatedPaymentIsAcknowledged(): void
    {
        $this->assertSame(200, $this->notify(['eventType' => 'Payment', 'data' => ['id' => 1234, 'state' => 'Pending']]));
    }

    public function testAMalformedNotificationIsRejected(): void
    {
        $this->assertSame(422, $this->notify(['eventType' => 'Payment', 'data' => 'not an object']));
    }

    /**
     * Target: a notification that does not prove it comes from HelloAsso
     * (shared secret, signature or source address) is refused before any
     * call to the HelloAsso API.
     */
    public function testANotificationThatDoesNotComeFromHelloassoIsRefused(): void
    {
        $outcome = $this->notify(['eventType' => 'Payment', 'data' => ['id' => 1234, 'state' => 'Authorized']]);

        $this->assertSecureOrKnownOpen('I-SEC-7', 'any notification of a validated payment triggers a HelloAsso API call', function () use ($outcome) {
            $this->assertContains($outcome, [401, 403]);
        });
    }

    /**
     * Target: data['id'] is a payment number, nothing else reaches the API path.
     */
    public function testAPaymentIdThatIsNotANumberIsRejected(): void
    {
        $outcome = $this->notify(['eventType' => 'Payment', 'data' => ['id' => '../organizations/someone-else/forms', 'state' => 'Authorized']]);

        $this->assertSecureOrKnownOpen('I-SEC-7', 'data[id] is pasted into the HelloAsso API path unchecked', function () use ($outcome) {
            $this->assertTrue(is_int($outcome) && $outcome >= 400 && $outcome < 500, sprintf('Answered %s, expected a 4xx.', $outcome));
        });
    }

    /**
     * Posts the notification and returns the status code, or a description of
     * the PHP error the controller died of (the test client, unlike the front
     * controller, lets errors through).
     *
     * @param array<string, mixed> $notification
     *
     * @return int|string
     */
    private function notify(array $notification)
    {
        $client = static::createClient();

        try {
            $client->request('POST', '/helloassoNotify', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($notification));
        } catch (\Error $error) {
            return sprintf('crashed: %s: %s', get_class($error), $error->getMessage());
        }

        return $client->getResponse()->getStatusCode();
    }
}
