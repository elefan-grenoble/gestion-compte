<?php

namespace App\Tests\Support;

use App\Entity\Job;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Data scenarios shared by the booking and shift controller tests.
 *
 * Needs PersistsEntities (FunctionalTestCase uses it). Dates are built from
 * "now" because the fixtures' dates are relative to the day they were loaded.
 *
 * @internal
 */
trait ShiftScenarios
{
    /**
     * A membership with one beneficiary, registered today.
     */
    protected static function aMembership(): Membership
    {
        return static::persist(MembershipBuilder::aMembership()->build());
    }

    /**
     * A free shift that a first-time shifter may book.
     *
     * New members start as beginners (NEW_USERS_START_AS_BEGINNER) and cannot
     * be the first to book a bucket, so another member already holds a shift
     * of the same bucket (same job, same start and end).
     *
     * @param null|\DateTime $start defaults to tomorrow 09:00, inside the delay in which extra shifts may be booked
     */
    protected static function aBookableShift(?\DateTime $start = null, int $minutes = 180, ?Job $job = null): Shift
    {
        $start ??= new \DateTime('tomorrow 09:00');

        $holder = static::aMembership()->getMainBeneficiary();
        $held = ShiftBuilder::aShift()->startingAt($start)->lasting($minutes)->bookedBy($holder);
        if (null !== $job) {
            $held->forJob($job);
        }
        $held = $held->build();
        static::persist($held->getJob(), $held);

        $free = ShiftBuilder::aShift()->startingAt($start)->lasting($minutes)->forJob($held->getJob())->build();
        static::persist($free);

        return $free;
    }

    /**
     * The entity as stored now, not as the identity map remembers it.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected static function reloaded(object $entity): object
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(get_class($entity), $entity->getId());
    }

    /**
     * The flash messages the last request left, by type.
     *
     * @return array<string, string[]>
     */
    protected static function flashes(KernelBrowser $client): array
    {
        return $client->getRequest()->getSession()->getFlashBag()->peekAll();
    }

    /**
     * Sends the JSON body the booking page posts to shift_book.
     */
    protected static function postJson(KernelBrowser $client, string $url, array $body): void
    {
        $client->request('POST', $url, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    /**
     * A valid CSRF token for the form of the given name, stored in the
     * session of the client's next request.
     *
     * Log the client in first: the token lives in that session.
     */
    protected static function csrfToken(KernelBrowser $client, string $tokenId): string
    {
        $container = $client->getContainer();
        $token = $container->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
        $container->get('session')->save();

        return $token;
    }

    /**
     * Runs the callable with environment variables set, as the application
     * reads its configuration from them each time a kernel boots.
     *
     * @param array<string, string> $variables
     *
     * @return mixed what the callable returns
     */
    protected static function withEnv(array $variables, callable $callable)
    {
        $previous = [];
        foreach ($variables as $name => $value) {
            $previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        try {
            return $callable();
        } finally {
            foreach ($previous as $name => [$env, $server]) {
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
        }
    }
}
