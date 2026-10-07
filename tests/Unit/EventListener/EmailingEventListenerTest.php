<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Code;
use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Event\CodeNewEvent;
use App\Event\HelloassoEvent;
use App\EventListener\EmailingEventListener;
use App\Helper\SwipeCard;
use App\Service\MembershipService;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\RuntimeError;
use App\Entity\User;

/**
 * @internal
 */
class EmailingEventListenerTest extends TestCase
{
    /**
     * C-BUG-1 (AP.7-1, fixed in b0c7097): a failure while building the "too
     * early to renew" email used to exit() with the message, killing the
     * HelloAsso webhook request with a blank page and no log. It must
     * surface as an exception instead.
     *
     * An exit() in the code under test would end the whole PHPUnit run with
     * status 0, the remaining tests silently unrun: failOnExit() turns that
     * into a failed run.
     */
    public function testAFailureBuildingTheTooEarlyEmailIsThrownNotExited(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willThrowException(new RuntimeError('template failure'));

        $membershipService = $this->createMock(MembershipService::class);
        $membershipService->method('getExpire')->willReturn(new \DateTime('+6 months'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $listener = $this->listener(['twig' => $twig, 'membership_service' => $membershipService], $mailer);
        $member = MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary())->build();

        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('template failure');

        $this->failOnExit(function () use ($listener, $member) {
            $listener->onHelloassoTooEarly(new HelloassoEvent(new HelloassoPayment(), self::mainUserOf($member)));
        });
    }

    /**
     * Runs $code; should it call exit(), makes the PHP process end with
     * status 1 (an exit() in a shutdown function sets the final status).
     */
    private function failOnExit(callable $code): void
    {
        $returned = false;
        register_shutdown_function(static function () use (&$returned) {
            if (!$returned) {
                fwrite(STDERR, PHP_EOL . 'EmailingEventListenerTest: the code under test called exit().' . PHP_EOL);

                exit(1);
            }
        });

        try {
            $code();
        } finally {
            $returned = true;
        }
    }

    /**
     * C-SEC-5 (fixed in b0c7097): the one-click link of the "new key box
     * code" email carries its issue time, without which CodeController
     * refuses it.
     */
    public function testTheNewCodeEmailLinkCarriesItsIssueTime(): void
    {
        $swipeCard = new SwipeCard('secret');
        $tokens = [];
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $route, array $parameters) use (&$tokens): string {
            $tokens[$route] = $parameters['token'] ?? null;

            return 'http://localhost/codes/close_all';
        });

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $listener = $this->listener([
            'router' => $router,
            'App\Helper\SwipeCard' => $swipeCard,
            'twig' => $this->createMock(Environment::class),
        ], $mailer);

        $registrar = self::mainUserOf(MembershipBuilder::aMembership()->build());
        $code = new Code();
        $code->setRegistrar($registrar);
        $code->setValue('1234');

        $before = time();
        $listener->onCodeNew(new CodeNewEvent($code, []));

        $this->assertArrayHasKey('code_change_done', $tokens);
        $this->assertMatchesRegularExpression('/,ts:(\d+)$/', $swipeCard->vigenereDecode($tokens['code_change_done']));
        preg_match('/,ts:(\d+)$/', $swipeCard->vigenereDecode($tokens['code_change_done']), $issuedAt);
        $this->assertGreaterThanOrEqual($before, (int) $issuedAt[1]);
        $this->assertLessThanOrEqual(time(), (int) $issuedAt[1]);
    }

    /**
     * @param array<string, object> $services
     */
    private function listener(array $services, MailerInterface $mailer): EmailingEventListener
    {
        $container = new Container(new ParameterBag([
            'due_duration_by_cycle' => 180,
            'emails.member' => ['address' => 'membres@yourcoop.local', 'from_name' => 'Membres'],
            'emails.shift' => ['address' => 'creneaux@yourcoop.local', 'from_name' => 'Créneaux'],
            'wiki_keys_url' => '',
            'reserve_new_shift_to_prior_shifter_delay' => 7,
            'locale' => 'fr_FR.UTF8',
        ]));
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return new EmailingEventListener(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(Logger::class),
            $container,
            $mailer,
            false,
            new SwipeCard('secret')
        );
    }

    private static function mainUserOf(Membership $member): User
    {
        return $member->getMainBeneficiary()->getUser();
    }
}
