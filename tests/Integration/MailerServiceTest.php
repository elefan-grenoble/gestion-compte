<?php

namespace App\Tests\Integration;

use App\Entity\DynamicContent;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\PersistsEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * The account mails sent through FOSUserBundle (confirmation, password reset).
 *
 * @internal
 */
class MailerServiceTest extends KernelTestCase
{
    use MailerAssertionsTrait;
    use PersistsEntities;

    protected function setUp(): void
    {
        static::bootKernel();
    }

    public function testConfirmationEmailCarriesTheWelcomeTextAndTheConfirmationLink(): void
    {
        $this->setWelcomeContent('Welcome aboard, **read the charter**.');
        $user = $this->aUser('confirm-token-123');

        static::$container->get('mailer_service')->sendConfirmationEmailMessage($user);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertSame('Bienvenue à My Local Coop', $email->getSubject());
        $this->assertEmailAddressContains($email, 'To', $user->getEmail());
        $this->assertEmailAddressContains($email, 'From', 'membres@yourcoop.local');
        $this->assertEmailHtmlBodyContains($email, '<strong>read the charter</strong>');
        $this->assertEmailHtmlBodyContains($email, 'confirm-token-123');
    }

    /**
     * I-BUG-4: WELCOME_EMAIL is read with findOneByCode()->getContent() and
     * no null guard; deleting the row breaks every account creation.
     */
    public function testConfirmationEmailSurvivesAMissingWelcomeContent(): void
    {
        $this->setWelcomeContent(null);
        $user = $this->aUser('confirm-token-123');

        static::$container->get('mailer_service')->sendConfirmationEmailMessage($user);

        $this->assertEmailCount(1);
    }

    public function testResettingEmailCarriesTheResetLink(): void
    {
        $user = $this->aUser('reset-token-456');

        static::$container->get('mailer_service')->sendResettingEmailMessage($user);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertSame('Réinitialisation de ton mot de passe', $email->getSubject());
        $this->assertEmailAddressContains($email, 'To', $user->getEmail());
        $this->assertEmailAddressContains($email, 'From', 'membres@yourcoop.local');
        $this->assertEmailHtmlBodyContains($email, 'reset-token-456');
    }

    private function aUser(string $token)
    {
        $user = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser();
        $user->setConfirmationToken($token);

        return $user;
    }

    private function setWelcomeContent(?string $content): void
    {
        $em = static::entityManager();
        $row = $em->getRepository(DynamicContent::class)->findOneBy(['code' => 'WELCOME_EMAIL']);

        if (null === $content) {
            if ($row) {
                $em->remove($row);
                $em->flush();
            }

            return;
        }

        if (!$row) {
            $row = new DynamicContent();
            $row->setCode('WELCOME_EMAIL');
            $row->setName('Welcome');
            $row->setDescription('Welcome');
            $row->setType('general');
        }
        $row->setContent($content);
        static::persist($row);
    }
}
