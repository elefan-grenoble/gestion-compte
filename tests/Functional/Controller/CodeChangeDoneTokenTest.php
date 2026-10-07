<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Code;
use App\Entity\User;
use App\Helper\SwipeCard;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;

/**
 * C-SEC-5 (SPEC.4, fixed in b0c7097): the emailed one-click link of
 * GET /codes/close_all logs its recipient in, for the request, to close the
 * codes older than theirs. Its token used to be valid forever; it now
 * carries its issue time and expires after 30 days.
 *
 * Each test sends the link anonymously, for a registrar whose newest code
 * makes an older code of someone else closable.
 *
 * @internal
 */
class CodeChangeDoneTokenTest extends FunctionalTestCase
{
    /** SWIPE_CARD_SECRET in .env.test, which also encrypts these tokens. */
    private const SECRET = 'SwipeSecretToEncryptNumberInUrl';

    /** CodeController::CODE_CHANGE_TOKEN_TTL */
    private const TTL = 30 * 24 * 60 * 60;

    public function testAFreshLinkClosesTheOlderCodes(): void
    {
        $client = static::createClient();
        [$registrar, $myCode, $olderCode] = $this->aRegistrarWithANewerCodeThan();

        $client->request('GET', '/codes/close_all?token=' . urlencode($this->token($registrar, $myCode, time())));

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertTrue($this->reloaded($olderCode)->getClosed(), 'A fresh link must still work.');
    }

    public function testALinkWithoutIssueTimeIsRefused(): void
    {
        $client = static::createClient();
        [$registrar, $myCode, $olderCode] = $this->aRegistrarWithANewerCodeThan();

        // The format of the links sent before the fix.
        $client->request('GET', '/codes/close_all?token=' . urlencode($this->encode($registrar->getUsername() . ',code:' . $myCode->getId())));

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertFalse($this->reloaded($olderCode)->getClosed());
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $client = static::createClient();
        [$registrar, $myCode, $olderCode] = $this->aRegistrarWithANewerCodeThan();

        $client->request('GET', '/codes/close_all?token=' . urlencode($this->token($registrar, $myCode, time() - self::TTL - 60)));

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertFalse($this->reloaded($olderCode)->getClosed());
    }

    /**
     * An admin registrar (who may close any code) with an open code, and an
     * older open code of another member.
     *
     * @return array{User, Code, Code}
     */
    private function aRegistrarWithANewerCodeThan(): array
    {
        $registrar = static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles('ROLE_ADMIN')))
                ->build()
        )->getMainBeneficiary()->getUser();
        $other = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser();

        $olderCode = $this->aCode($other, new \DateTime('-2 hours'));
        $myCode = $this->aCode($registrar, new \DateTime('-1 hour'));

        return [$registrar, $myCode, $olderCode];
    }

    private function aCode(User $registrar, \DateTime $createdAt): Code
    {
        $code = new Code();
        $code->setValue((string) random_int(1000, 9999));
        $code->setRegistrar($registrar);
        $code->setClosed(false);
        $code->setCreatedAt($createdAt);

        return static::persist($code);
    }

    /**
     * The token as EmailingEventListener and VerifyCodeChangeCommand build it.
     */
    private function token(User $registrar, Code $code, int $issuedAt): string
    {
        return $this->encode($registrar->getUsername() . ',code:' . $code->getId() . ',ts:' . $issuedAt);
    }

    private function encode(string $value): string
    {
        return (new SwipeCard(self::SECRET))->vigenereEncode($value);
    }

    private function reloaded(Code $code): Code
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(Code::class, $code->getId());
    }
}
