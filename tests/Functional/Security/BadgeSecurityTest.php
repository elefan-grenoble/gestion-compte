<?php

namespace App\Tests\Functional\Security;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Entity\SwipeCard;
use App\Entity\User;
use App\Helper\QrCodePng;
use App\Helper\SwipeCard as SwipeCardHelper;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Security\KnownOpenVulnerability;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Badges (swipe cards): the card reader (I-SEC-4), the badge images (I-SEC-9)
 * and the badge management forms (I-SEC-6).
 *
 * @internal
 */
class BadgeSecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    /** SWIPE_CARD_SECRET in .env.test. */
    private const SWIPE_CARD_SECRET = 'SwipeSecretToEncryptNumberInUrl';

    /** The badge management forms redirect to where they were sent from. */
    private const REFERER = ['HTTP_REFERER' => 'http://localhost/profile/'];

    /**
     * I-SEC-4 (SEC.2-2, SPEC.3): /card_reader/check is public, so anyone who
     * knows (or forges, C-SEC-4) a badge number validates its holder's shift.
     */
    public function testAnonymousVisitorCannotValidateAShiftWithABadgeNumber(): void
    {
        $client = static::createClient();
        $holder = $this->aMember()->getBeneficiary();
        $card = $this->aBadge($holder);
        $shift = ShiftBuilder::aShift()->startingAt(new \DateTime('-1 hour'))->bookedBy($holder)->build();
        static::persist($shift->getJob(), $shift);

        $client->request('POST', '/card_reader/check', ['swipe_code' => self::ean13($card->getCode())]);

        $this->assertSecureOrKnownOpen('I-SEC-4', 'an anonymous POST with a badge number validates the holder\'s ongoing shift', function () use ($shift) {
            static::entityManager()->clear();
            $this->assertFalse(
                (bool) static::entityManager()->find(Shift::class, $shift->getId())->getWasCarriedOut(),
                'An anonymous request validated the shift.'
            );
        });
    }

    /**
     * I-SEC-9 (SPEC.4): the badge images are public; whoever gets the
     * encoded code (it is in the URL) prints a working copy of the badge.
     *
     * The QR code (/sw/{code}/qr.png) serves the same badge: see
     * testTheHolderDownloadsTheQrCodeOfTheirBadge.
     */
    public function testAnonymousVisitorCannotDownloadABadgeImage(): void
    {
        $client = static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client->request('GET', sprintf('/sw/%s/br.png', urlencode(self::encode($card->getCode()))));

        $this->assertSecureOrKnownOpen('I-SEC-9', 'an anonymous visitor downloads the barcode of a badge', function () use ($client) {
            $response = $client->getResponse();
            $this->assertTrue(
                $response->isRedirect('http://localhost/login') || 403 === $response->getStatusCode(),
                sprintf('Answered %d (%s), expected a login or a 403.', $response->getStatusCode(), $response->headers->get('Content-Type'))
            );
        });
    }

    /**
     * The QR code of a badge is a PNG of the badge link (C-BUG-7: the route
     * called the endroid/qr-code 3 API while version 4 is installed, and
     * crashed whoever asked).
     */
    public function testTheHolderDownloadsTheQrCodeOfTheirBadge(): void
    {
        static::createClient();
        $holder = $this->aMember();
        $card = $this->aBadge($holder->getBeneficiary());

        $client = static::createAuthenticatedClient($holder);
        $client->request('GET', sprintf('/sw/%s/qr.png', urlencode(self::encode($card->getCode()))));

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
        $this->assertSame([QrCodePng::SIZE, QrCodePng::SIZE], array_slice(getimagesizefromstring($response->getContent()), 0, 2));
    }

    /**
     * I-SEC-9: the target is the badge holder and the user managers only.
     */
    public function testAnotherMemberCannotDownloadABadgeImage(): void
    {
        static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client = static::createAuthenticatedClient($this->aMember());
        $client->request('GET', sprintf('/sw/%s/br.png', urlencode(self::encode($card->getCode()))));

        $this->assertSecureOrKnownOpen('I-SEC-9', 'any member downloads the barcode of another member\'s badge', function () use ($client) {
            $this->assertSame(403, $client->getResponse()->getStatusCode());
        });
    }

    /**
     * I-SEC-6 (SEC.3-5): no CSRF token on the badge forms; a forged POST from
     * any page disables the badge of whoever opens it (no shop access).
     */
    public function testAForgedPostCannotDisableABadge(): void
    {
        static::createClient();
        $holder = $this->aMember();
        $card = $this->aBadge($holder->getBeneficiary());

        $client = static::createAuthenticatedClient($holder);
        $client->request('POST', '/sw/disable', [
            'code' => self::encode($card->getCode()),
            'beneficiary' => $holder->getBeneficiary()->getId(),
        ], [], self::REFERER);

        $this->assertSecureOrKnownOpen('I-SEC-6', 'a POST without CSRF token disables a badge', function () use ($card) {
            $this->assertTrue($this->reloaded($card)->getEnable(), 'A POST without CSRF token disabled the badge.');
        });
    }

    /**
     * I-SEC-6: same for re-enabling a disabled badge.
     */
    public function testAForgedPostCannotEnableABadge(): void
    {
        static::createClient();
        $holder = $this->aMember();
        $card = $this->aBadge($holder->getBeneficiary(), false);

        $client = static::createAuthenticatedClient($holder);
        $client->request('POST', '/sw/enable', [
            'code' => self::encode($card->getCode()),
            'beneficiary' => $holder->getBeneficiary()->getId(),
        ], [], self::REFERER);

        $this->assertSecureOrKnownOpen('I-SEC-6', 'a POST without CSRF token re-enables a badge', function () use ($card) {
            $this->assertFalse($this->reloaded($card)->getEnable(), 'A POST without CSRF token enabled the badge.');
        });
    }

    /**
     * I-SEC-6: pairing a badge the attacker holds with the victim's account.
     */
    public function testAForgedPostCannotPairABadge(): void
    {
        static::createClient();
        $victim = $this->aMember();

        $client = static::createAuthenticatedClient($victim);
        $client->request('POST', '/sw/activate', [
            'code' => self::ean13('200000000042'),
            'beneficiary' => $victim->getBeneficiary()->getId(),
        ], [], self::REFERER);

        $this->assertSecureOrKnownOpen('I-SEC-6', 'a POST without CSRF token pairs a badge with the account', function () {
            $this->assertSame(0, static::entityManager()->getRepository(SwipeCard::class)->count(['code' => '200000000042']), 'A POST without CSRF token paired the badge.');
        });
    }

    /**
     * I-SEC-6: deleting a badge, for an admin.
     */
    public function testAForgedPostCannotDeleteABadge(): void
    {
        static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client = static::createAuthenticatedClient($this->aMember('ROLE_ADMIN'));
        $client->request('POST', '/sw/delete', ['code' => self::encode($card->getCode())], [], self::REFERER);

        $this->assertSecureOrKnownOpen('I-SEC-6', 'a POST without CSRF token deletes a badge', function () use ($card) {
            static::entityManager()->clear();
            $this->assertNotNull(static::entityManager()->find(SwipeCard::class, $card->getId()), 'A POST without CSRF token deleted the badge.');
        });
    }

    /**
     * The badge forms themselves stay closed to anonymous visitors.
     *
     * @dataProvider badgeForms
     */
    public function testAnonymousVisitorCannotPostTheBadgeForms(string $path): void
    {
        $client = static::createClient();
        $client->request('POST', $path, [], [], self::REFERER);

        $this->assertLoginRequired($client);
    }

    /**
     * @return array<string, array{string}>
     */
    public function badgeForms(): array
    {
        return [
            'activate' => ['/sw/activate'],
            'enable' => ['/sw/enable'],
            'disable' => ['/sw/disable'],
            'delete' => ['/sw/delete'],
        ];
    }

    private function aMember(?string $role = null): User
    {
        $user = UserBuilder::aUser();
        if (null !== $role) {
            $user->withRoles($role);
        }

        return static::persist(
            MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser($user))->build()
        )->getMainBeneficiary()->getUser();
    }

    private function aBadge(Beneficiary $holder, bool $enabled = true): SwipeCard
    {
        $card = new SwipeCard();
        $card->setBeneficiary($holder);
        $card->setCode('20000000' . str_pad((string) $holder->getId(), 4, '0', STR_PAD_LEFT));
        $card->setNumber(1);
        $card->setEnable($enabled);

        return static::persist($card);
    }

    private function reloaded(SwipeCard $card): SwipeCard
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(SwipeCard::class, $card->getId());
    }

    private function assertLoginRequired(KernelBrowser $client): void
    {
        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    /**
     * A 12-digit badge code with its EAN-13 check digit, as a reader sends it.
     */
    private static function ean13(string $code): string
    {
        foreach (range(0, 9) as $digit) {
            if (SwipeCard::checkEAN13($code . $digit)) {
                return $code . $digit;
            }
        }

        throw new \LogicException(sprintf('No EAN-13 check digit for %s.', $code));
    }

    /**
     * The code as it appears in badge URLs and forms.
     */
    private static function encode(string $code): string
    {
        return (new SwipeCardHelper(self::SWIPE_CARD_SECRET))->vigenereEncode($code);
    }
}
