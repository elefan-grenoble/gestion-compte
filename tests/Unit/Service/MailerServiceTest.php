<?php

namespace App\Tests\Unit\Service;

use App\Service\MailerService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Router;

/**
 * @internal
 */
class MailerServiceTest extends TestCase
{
    private function createService(string $baseDomain = 'yourcoop.local', array $sendableEmails = []): MailerService
    {
        return new MailerService(
            $this->createMock(MailerInterface::class),
            $baseDomain,
            ['address' => 'membres@yourcoop.local', 'from_name' => 'Membres'],
            'My Coop',
            $sendableEmails,
            $this->createMock(EntityManager::class),
            $this->createMock(Router::class),
            new \stdClass()
        );
    }

    /**
     * @dataProvider temporaryEmailProvider
     */
    public function testIsTemporaryEmail(string $email, bool $expected): void
    {
        $this->assertSame($expected, $this->createService()->isTemporaryEmail($email));
    }

    public function temporaryEmailProvider(): array
    {
        return [
            'placeholder address of the base domain' => ['membres+42@yourcoop.local', true],
            'case insensitive' => ['MEMBRES+42@YourCoop.Local', true],
            'many digits' => ['membres+1234567@yourcoop.local', true],
            'real address' => ['jane.doe@example.org', false],
            'placeholder on another domain' => ['membres+42@other.local', false],
            'plus without digits' => ['membres+abc@yourcoop.local', false],
            'no plus tag' => ['membres@yourcoop.local', false],
            'empty' => ['', false],
        ];
    }

    public function testIsTemporaryEmailEscapesTheBaseDomain(): void
    {
        $service = $this->createService('yourcoop.local');

        // the dot of the domain must not act as a regex wildcard
        $this->assertFalse($service->isTemporaryEmail('membres+42@yourcoopXlocal'));
    }

    public function testGetAllowedEmailsIndexesAddressesByDisplayName(): void
    {
        $service = $this->createService('yourcoop.local', [
            ['address' => 'contact@yourcoop.local', 'from_name' => 'Contact'],
            ['address' => 'creneaux@yourcoop.local', 'from_name' => 'Créneaux'],
        ]);

        $this->assertSame([
            'Contact <contact@yourcoop.local>' => 'contact@yourcoop.local',
            'Créneaux <creneaux@yourcoop.local>' => 'creneaux@yourcoop.local',
        ], $service->getAllowedEmails());
    }

    public function testGetAllowedEmailsIsEmptyWithoutSendableEmails(): void
    {
        $this->assertSame([], $this->createService()->getAllowedEmails());
    }
}
