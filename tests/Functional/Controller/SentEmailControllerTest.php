<?php

namespace App\Tests\Functional\Controller;

use App\Entity\SentEmail;
use App\Tests\Functional\FunctionalTestCase;

/**
 * The history of the sent mails in the admin: list, filters, detail.
 *
 * @internal
 */
class SentEmailControllerTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testTheListShowsTheMailsNewestFirstWithTheirState(): void
    {
        $client = $this->loginAs('admin');
        $this->aMail('Older mail', 'old@example.org', SentEmail::STATUS_SENT, '-2 days');
        $this->aMail('Newer mail', 'new@example.org', SentEmail::STATUS_FAILED, '-1 hour');

        $crawler = $client->request('GET', '/admin/mails/historique/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $text = $crawler->filterXPath('//tbody')->text();
        $this->assertStringContainsString('Older mail', $text);
        $this->assertLessThan(strpos($text, 'Older mail'), strpos($text, 'Newer mail'));
        $this->assertStringContainsString('Échec', $text);
        $this->assertStringContainsString('Envoyé', $text);
    }

    public function testTheListCanBeFilteredByStatus(): void
    {
        $client = $this->loginAs('admin');
        $this->aMail('Delivered mail', 'ok@example.org', SentEmail::STATUS_SENT, '-1 hour');
        $this->aMail('Refused mail', 'ko@example.org', SentEmail::STATUS_FAILED, '-1 hour');

        $crawler = $client->request('GET', '/admin/mails/historique/');
        $crawler = $client->submit($crawler->selectButton('Filtrer')->form(['form[status]' => SentEmail::STATUS_FAILED]));

        $text = $crawler->filterXPath('//tbody')->text();
        $this->assertStringContainsString('Refused mail', $text);
        $this->assertStringNotContainsString('Delivered mail', $text);
    }

    public function testAMailBeingHandedOverIsShownAsWaiting(): void
    {
        $client = $this->loginAs('admin');
        $this->aMail('Handed over mail', 'p@example.org', SentEmail::STATUS_PENDING, '-1 hour');
        $this->aMail('Delivered waiting test', 'ok@example.org', SentEmail::STATUS_SENT, '-1 hour');

        $crawler = $client->request('GET', '/admin/mails/historique/');
        $this->assertStringContainsString('En attente', $crawler->filterXPath('//tbody')->text());

        $crawler = $client->submit($crawler->selectButton('Filtrer')->form(['form[status]' => SentEmail::STATUS_PENDING]));
        $text = $crawler->filterXPath('//tbody')->text();
        $this->assertStringContainsString('Handed over mail', $text);
        $this->assertStringNotContainsString('Delivered waiting test', $text);
    }

    public function testTheMailsOfAGroupAreOneItemOfTheListAndOpenIntoTheirOwnList(): void
    {
        $client = $this->loginAs('admin');
        $batch = bin2hex(random_bytes(16));
        foreach (['ann@example.org', 'bob@example.org', 'cat@example.org'] as $i => $to) {
            $mail = $this->aMail('Group news', $to, 0 === $i ? SentEmail::STATUS_FAILED : SentEmail::STATUS_SENT, '-1 hour', SentEmail::SOURCE_MANUAL);
            static::persist($mail->setBatchId($batch));
        }
        $this->aMail('Lonely mail', 'solo@example.org', SentEmail::STATUS_SENT, '-2 hours', SentEmail::SOURCE_MANUAL);

        $crawler = $client->request('GET', '/admin/mails/historique/');

        $text = $crawler->filterXPath('//tbody')->text();
        $this->assertSame(1, substr_count($text, 'Group news'), 'The group is one item.');
        $this->assertStringContainsString('3 destinataires', $text);
        $this->assertStringContainsString('avec des échecs', $text);
        $this->assertStringContainsString('Lonely mail', $text);

        $crawler = $client->click($crawler->filterXPath('//a[contains(@href, "/groupe/")]')->link());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $text = $crawler->filterXPath('//tbody')->text();
        foreach (['ann@example.org', 'bob@example.org', 'cat@example.org'] as $to) {
            $this->assertStringContainsString($to, $text);
        }
        $this->assertStringNotContainsString('Lonely mail', $text);
    }

    public function testTheDetailShowsTheErrorAndTheBodyOfAWrittenMail(): void
    {
        $client = $this->loginAs('admin');
        $mail = $this->aMail('Written mail', 'ko@example.org', SentEmail::STATUS_FAILED, '-1 hour', SentEmail::SOURCE_MANUAL);
        $mail->setError('550 Mailbox unavailable')->setBody('<p>Dear member</p>');
        static::persist($mail);

        $crawler = $client->request('GET', '/admin/mails/historique/' . $mail->getId());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('550 Mailbox unavailable', $crawler->text());
        $this->assertStringContainsString('Dear member', $crawler->filterXPath('//iframe')->attr('srcdoc'));
    }

    private function aMail(string $subject, string $recipient, string $status, string $when, string $source = SentEmail::SOURCE_AUTO): SentEmail
    {
        $mail = (new SentEmail())
            ->setSubject($subject)
            ->setFromAddress('membres@yourcoop.local')
            ->setRecipients($recipient)
            ->setRecipientCount(1)
            ->setStatus($status)
            ->setSource($source)
            ->setCreatedAt(new \DateTime($when))
        ;

        return static::persist($mail);
    }
}
