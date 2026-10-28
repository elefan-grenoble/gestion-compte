<?php

namespace App\Tests\Functional;

use App\Tests\Support\Builder\MembershipBuilder;

/**
 * Regression tests for the quick wins of b0c7097 that the other test
 * classes do not cover: a mistyped parameter and a third-party script.
 *
 * C-BUG-1, C-BUG-3 and C-SEC-5, from the same commit, have their own tests
 * (EmailingEventListenerTest, EventProxyTakeTest, CodeChangeDoneTokenTest).
 *
 * @internal
 */
class AuditQuickWinsRegressionTest extends FunctionalTestCase
{
    /**
     * reserve_new_shift_to_prior_shifter_delay is a number of days, but was
     * read through the bool: env processor, which turned 7 into true.
     */
    public function testThePriorShifterReservationDelayIsANumberOfDays(): void
    {
        static::bootKernel();
        $delay = static::$container->getParameter('reserve_new_shift_to_prior_shifter_delay');

        $this->assertIsNotBool($delay);
        $this->assertSame(7, (int) $delay, 'RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY is 7 in .env.test.');
    }

    /**
     * The canvas-gauges script came from cdn.rawgit.com, a CDN shut down
     * since, without integrity check; it is bundled by Encore now. No page
     * loads a script from another host.
     *
     * @dataProvider pages
     */
    public function testPagesLoadNoThirdPartyScript(string $path, bool $loggedIn): void
    {
        if ($loggedIn) {
            static::createClient();
            $client = static::createAuthenticatedClient(static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser());
        } else {
            $client = static::createClient();
        }

        $crawler = $client->request('GET', $path);
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $thirdParty = array_values(array_filter(
            $crawler->filterXPath('//script[@src]')->extract(['src']),
            static function (string $src): bool {
                $host = parse_url($src, PHP_URL_HOST);

                return null !== $host && 'localhost' !== $host;
            }
        ));
        $this->assertSame([], $thirdParty);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function pages(): array
    {
        return [
            'login page' => ['/login', false],
            'member home page' => ['/', true],
        ];
    }
}
