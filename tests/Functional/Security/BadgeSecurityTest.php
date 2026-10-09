<?php

namespace App\Tests\Functional\Security;

use App\Controller\SwipeCardController;
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
     * The badge images are restricted to the badge holder and the user
     * managers (I-SEC-9, SPEC.4: they were public, whoever got the encoded
     * code, which is in the URL, printed a working copy of the badge).
     *
     * @dataProvider badgeImages
     */
    public function testAnonymousVisitorCannotDownloadABadgeImage(string $image): void
    {
        $client = static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client->request('GET', sprintf('/sw/%s/%s', urlencode(self::encode($card->getCode())), $image));

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    /**
     * @dataProvider badgeImages
     */
    public function testAnotherMemberCannotDownloadABadgeImage(string $image): void
    {
        static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client = static::createAuthenticatedClient($this->aMember());
        $client->request('GET', sprintf('/sw/%s/%s', urlencode(self::encode($card->getCode())), $image));

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider badgeImages
     */
    public function testAUserManagerDownloadsTheBadgeImageOfAMember(string $image): void
    {
        static::createClient();
        $card = $this->aBadge($this->aMember()->getBeneficiary());

        $client = static::createAuthenticatedClient($this->aMember('ROLE_USER_MANAGER'));
        $client->request('GET', sprintf('/sw/%s/%s', urlencode(self::encode($card->getCode())), $image));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function badgeImages(): array
    {
        return ['barcode' => ['br.png'], 'QR code' => ['qr.png']];
    }

    /**
     * The badge forms carry a CSRF token (I-SEC-6, SEC.3-5: without it, a
     * forged POST from any page disabled the badge of whoever opened it, no
     * shop access).
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

        $this->assertTrue($this->reloaded($card)->getEnable(), 'A POST without CSRF token disabled the badge.');
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

        $this->assertFalse($this->reloaded($card)->getEnable(), 'A POST without CSRF token enabled the badge.');
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

        $this->assertSame(0, static::entityManager()->getRepository(SwipeCard::class)->count(['code' => '200000000042']), 'A POST without CSRF token paired the badge.');
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

        static::entityManager()->clear();
        $this->assertNotNull(static::entityManager()->find(SwipeCard::class, $card->getId()), 'A POST without CSRF token deleted the badge.');
    }

    /**
     * The forms of the profile page carry the token: the holder disables,
     * re-enables and pairs their badge.
     */
    public function testTheHolderManagesTheirBadgeWithTheToken(): void
    {
        static::createClient();
        $holder = $this->aMember();
        $card = $this->aBadge($holder->getBeneficiary());
        $client = static::createAuthenticatedClient($holder);
        $token = $client->getContainer()->get('security.csrf.token_manager')->getToken(SwipeCardController::CSRF_TOKEN_ID)->getValue();
        $client->getContainer()->get('session')->save();
        $form = ['code' => self::encode($card->getCode()), 'beneficiary' => $holder->getBeneficiary()->getId(), '_token' => $token];

        $client->request('POST', '/sw/disable', $form, [], self::REFERER);
        $this->assertFalse($this->reloaded($card)->getEnable());

        $client->request('POST', '/sw/enable', $form, [], self::REFERER);
        $this->assertTrue($this->reloaded($card)->getEnable());
    }

    public function testTheProfilePageFormsCarryTheToken(): void
    {
        static::createClient();
        $holder = $this->aMember();
        $this->aBadge($holder->getBeneficiary());
        $client = static::createAuthenticatedClient($holder);

        $crawler = $client->request('GET', '/profile/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $forms = $crawler->filterXPath('//form[contains(@action, "/sw/")]');
        $this->assertGreaterThan(0, $forms->count());
        $this->assertSame($forms->count(), $crawler->filterXPath('//form[contains(@action, "/sw/")]//input[@name="_token"]')->count());
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
