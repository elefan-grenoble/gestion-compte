<?php

namespace App\Tests\Functional\Security;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Security\KnownOpenVulnerability;

/**
 * ShiftController routes without a @Security annotation: the co-shifters
 * contact form (I-SEC-2) and the acceptance or refusal of a shift reserved
 * for its previous shifter (I-SEC-3).
 *
 * @internal
 */
class ShiftSecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    /**
     * I-SEC-2 (SEC.1-3): the form mailed the co-shifters on behalf of anyone.
     * Fixed by the default-deny access_control rule (C-SEC-2).
     *
     * @dataProvider methods
     */
    public function testAnonymousVisitorCannotReachTheContactForm(string $method): void
    {
        $client = static::createClient();
        $shift = $this->aShift();

        $client->request($method, sprintf('/shift/%d/contact_form', $shift->getId()), ['form' => ['message' => 'spam']]);

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function methods(): array
    {
        return ['GET' => ['GET'], 'POST' => ['POST']];
    }

    /**
     * I-SEC-3, first half: the emailed link let an anonymous visitor holding
     * the token decide for the shifter. Since the default-deny rule (C-SEC-2)
     * the link asks for a login first.
     *
     * @dataProvider decisions
     */
    public function testAnonymousVisitorWithTheEmailedTokenIsAskedToLogIn(string $decision): void
    {
        $client = static::createClient();
        [$shift, $shifter] = $this->aShiftReservedForItsPreviousShifter();

        $client->request('GET', sprintf('/shift/%d/%s?token=%s', $shift->getId(), $decision, $shift->getTmpToken($shifter->getId())));

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
        $this->assertSame($shifter->getId(), $this->reloaded($shift)->getLastShifter()->getId(), 'The reservation was decided anonymously.');
    }

    /**
     * @return array<string, array{string}>
     */
    public function decisions(): array
    {
        return ['accept' => ['accept'], 'reject' => ['reject']];
    }

    /**
     * I-SEC-3, second half: accepting is a GET without a CSRF token, so any
     * page the shifter opens can book the shift in their name.
     */
    public function testAGetLinkCannotAcceptAReservedShift(): void
    {
        static::createClient();
        [$shift, $shifter] = $this->aShiftReservedForItsPreviousShifter();

        $client = static::createAuthenticatedClient($shifter->getUser());
        $client->request('GET', sprintf('/shift/%d/accept', $shift->getId()));

        $this->assertSecureOrKnownOpen('I-SEC-3', 'a GET link (no CSRF token) books a reserved shift for the logged-in shifter', function () use ($shift) {
            $this->assertFalse(null !== $this->reloaded($shift)->getShifter(), 'The shift was booked by a GET request.');
        });
    }

    /**
     * I-SEC-3, same for the refusal, which frees the reservation.
     */
    public function testAGetLinkCannotRejectAReservedShift(): void
    {
        static::createClient();
        [$shift, $shifter] = $this->aShiftReservedForItsPreviousShifter();

        $client = static::createAuthenticatedClient($shifter->getUser());
        $client->request('GET', sprintf('/shift/%d/reject', $shift->getId()));

        $this->assertSecureOrKnownOpen('I-SEC-3', 'a GET link (no CSRF token) gives up a reserved shift for the logged-in shifter', function () use ($shift) {
            $this->assertNotNull($this->reloaded($shift)->getLastShifter(), 'The reservation was given up by a GET request.');
        });
    }

    /**
     * Another member cannot decide for the shifter, token or not.
     */
    public function testAnotherMemberCannotAcceptAReservedShift(): void
    {
        static::createClient();
        [$shift] = $this->aShiftReservedForItsPreviousShifter();
        $other = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser();

        $client = static::createAuthenticatedClient($other);
        $client->request('GET', sprintf('/shift/%d/accept', $shift->getId()));

        $this->assertFalse(null !== $this->reloaded($shift)->getShifter());
    }

    private function aShift(): Shift
    {
        $shift = ShiftBuilder::aShift()->build();
        static::persist($shift->getJob(), $shift);

        return $shift;
    }

    /**
     * A free shift reserved for the member who held it last cycle.
     *
     * @return array{Shift, Beneficiary}
     */
    private function aShiftReservedForItsPreviousShifter(): array
    {
        $shifter = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()->build();
        $shift->setLastShifter($shifter);
        static::persist($shift->getJob(), $shift);

        return [$shift, $shifter];
    }

    private function reloaded(Shift $shift): Shift
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(Shift::class, $shift->getId());
    }
}
