<?php

namespace App\Tests\Integration;

use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Code;
use App\Entity\DynamicContent;
use App\Entity\Event;
use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Entity\Proxy;
use App\Entity\Shift;
use App\Event\AnonymousBeneficiaryCreatedEvent;
use App\Event\AnonymousBeneficiaryRecallEvent;
use App\Event\BeneficiaryAddEvent;
use App\Event\CodeNewEvent;
use App\Event\EventProxyCreatedEvent;
use App\Event\HelloassoEvent;
use App\Event\MemberCycleHalfEvent;
use App\Event\MemberCycleStartEvent;
use App\Event\ShiftAlertsEvent;
use App\Event\ShiftBookedEvent;
use App\Event\ShiftDeletedEvent;
use App\Event\ShiftFreedEvent;
use App\Event\ShiftReminderEvent;
use App\Event\ShiftReservedEvent;
use App\EventListener\EmailingEventListener;
use App\Helper\SwipeCard;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\PersistsEntities;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Mime\Email;

/**
 * What the members and the admins receive, event by event. The listener is
 * wired to the real mailer (null transport): the messages are read back with
 * the mailer assertions of the framework.
 *
 * Addresses come from .env.test: members@ / shifts@ ... yourcoop.local.
 *
 * @internal
 */
class EmailingEventListenerTest extends KernelTestCase
{
    use MailerAssertionsTrait;
    use PersistsEntities;

    private const MEMBER_ADDRESS = 'membres@yourcoop.local';
    private const SHIFT_ADDRESS = 'creneaux@yourcoop.local';

    protected function setUp(): void
    {
        static::bootKernel();
    }

    // ---------------------------------------------------------------------
    // wiring
    // ---------------------------------------------------------------------

    /**
     * @dataProvider subscribedEventsProvider
     */
    public function testListenerIsSubscribedToTheMailingEvents(string $eventName, string $method): void
    {
        $subscribed = [];
        foreach (static::$container->get('event_dispatcher')->getListeners($eventName) as $listener) {
            if (is_array($listener) && $listener[0] instanceof EmailingEventListener) {
                $subscribed[] = $listener[1];
            }
        }

        $this->assertSame([$method], $subscribed);
    }

    public function subscribedEventsProvider(): array
    {
        return [
            ['shift.reserved', 'onShiftReserved'],
            ['shift.booked', 'onShiftBooked'],
            ['shift.freed', 'onShiftFreed'],
            ['shift.reminder', 'onShiftReminder'],
            ['shift.deleted', 'onShiftDeleted'],
            ['shift.alerts', 'onShiftAlerts'],
            ['member.cycle.start', 'onMemberCycleStart'],
            ['member.cycle.half', 'onMemberCycleHalf'],
            ['anonymous_beneficiary.created', 'onAnonymousBeneficiaryCreated'],
            ['anonymous_beneficiary.recall', 'onAnonymousBeneficiaryRecall'],
            ['beneficiary.add', 'onBeneficiaryAdd'],
            ['event.proxy.created', 'onEventProxyCreated'],
            ['helloasso.registration_success', 'onHelloassoRegistrationSuccess'],
            ['helloasso.too_early', 'onHelloassoTooEarly'],
            ['code.new', 'onCodeNew'],
        ];
    }

    // ---------------------------------------------------------------------
    // registration
    // ---------------------------------------------------------------------

    public function testNewAnonymousBeneficiaryReceivesTheRegistrationLink(): void
    {
        $this->setDynamicContent('PRE_MEMBERSHIP_EMAIL', 'Please bring **your ID**.');
        $anonymous = $this->anAnonymousBeneficiary('newcomer@example.org');

        $this->listener()->onAnonymousBeneficiaryCreated(new AnonymousBeneficiaryCreatedEvent($anonymous));

        $email = $this->theOnlyEmail();
        $this->assertSubject('Bienvenue à My Local Coop, tu te présentes ?', $email);
        $this->assertAddress('To', 'newcomer@example.org', $email);
        $this->assertAddress('From', self::MEMBER_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, '/member/new?code=');
        $this->assertEmailHtmlBodyContains($email, '<strong>your ID</strong>');
    }

    public function testNewAnonymousBeneficiaryJoiningAMembershipReceivesTheAddBeneficiaryLink(): void
    {
        $this->setDynamicContent('PRE_MEMBERSHIP_EMAIL', 'Welcome');
        $host = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $anonymous = $this->anAnonymousBeneficiary('partner@example.org');
        $anonymous->setJoinTo($host);

        $this->listener()->onAnonymousBeneficiaryCreated(new AnonymousBeneficiaryCreatedEvent($anonymous));

        $this->assertEmailHtmlBodyNotContains($this->theOnlyEmail(), '/member/new?code=');
        $this->assertEmailHtmlBodyContains($this->theOnlyEmail(), '/member/add_beneficiary?code=');
    }

    public function testRecallOfAnAnonymousBeneficiaryMentionsTheRegistrationDate(): void
    {
        $this->setDynamicContent('PRE_MEMBERSHIP_EMAIL', 'Still time to register');
        $anonymous = $this->anAnonymousBeneficiary('newcomer@example.org');

        $this->listener()->onAnonymousBeneficiaryRecall(new AnonymousBeneficiaryRecallEvent($anonymous));

        $email = $this->theOnlyEmail();
        $this->assertSubject('Bienvenue à My Local Coop, souhaites-tu te présenter ?', $email);
        $this->assertAddress('To', 'newcomer@example.org', $email);
        $this->assertEmailHtmlBodyContains($email, 'Still time to register');
        $this->assertEmailHtmlBodyContains($email, '/member/new?code=');
    }

    /**
     * I-BUG-4: the dynamic content is read with findOneByCode(...)->getContent()
     * and no null guard. A coop that deleted the row crashes the whole
     * registration flow; the mail should go out without the dynamic part.
     *
     * @dataProvider missingPreMembershipContentProvider
     */
    public function testAnonymousBeneficiaryMailsSurviveAMissingDynamicContent(string $method, string $eventClass): void
    {
        $this->markTestIncomplete('I-BUG-4 open: EmailingEventListener reads PRE_MEMBERSHIP_EMAIL with findOneByCode()->getContent() and no null guard (Error on null).');

        $this->setDynamicContent('PRE_MEMBERSHIP_EMAIL', null);
        $anonymous = $this->anAnonymousBeneficiary('newcomer@example.org');

        $this->listener()->{$method}(new $eventClass($anonymous));

        $this->assertEmailCount(1);
    }

    public function missingPreMembershipContentProvider(): array
    {
        return [
            'created' => ['onAnonymousBeneficiaryCreated', AnonymousBeneficiaryCreatedEvent::class],
            'recall' => ['onAnonymousBeneficiaryRecall', AnonymousBeneficiaryRecallEvent::class],
        ];
    }

    public function testOwnerIsToldWhenABeneficiaryIsAddedToTheirAccount(): void
    {
        $membership = static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Olivia', 'Owner'))
                ->withBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Bob', 'Partner'))
                ->build()
        );
        $owner = $membership->getMainBeneficiary();
        $added = $membership->getBeneficiaries()->filter(function (Beneficiary $b) use ($owner) {
            return $b !== $owner;
        })->first();

        $this->listener()->onBeneficiaryAdd(new BeneficiaryAddEvent($added));

        $email = $this->theOnlyEmail();
        $this->assertSubject('Bob a été ajouté à ton compte My Local Coop', $email);
        $this->assertAddress('To', $owner->getEmail(), $email);
        $this->assertAddress('From', self::MEMBER_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, 'Bonjour Olivia');
        $this->assertEmailHtmlBodyContains($email, 'Bob PARTNER');
    }

    public function testFirstHelloassoRegistrationIsAcknowledged(): void
    {
        $user = $this->aMemberUser();
        $payment = $this->aPayment(42.5);

        $this->listener()->onHelloassoRegistrationSuccess(new HelloassoEvent($payment, $user));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Adhésion helloasso bien reçue !', $email);
        $this->assertAddress('To', $user->getEmail(), $email);
        $this->assertAddress('From', 'contact@yourcoop.local', $email);
        $this->assertEmailHtmlBodyContains($email, 'ton adhésion d\'un montant de 42.5€');
    }

    public function testHelloassoReRegistrationIsAcknowledgedAsSuch(): void
    {
        $user = $this->aMemberUser(function (MembershipBuilder $builder) {
            $builder->registeredOn(new \DateTime('-2 years'), new \DateTime('today'));
        });
        $payment = $this->aPayment(10);

        $this->listener()->onHelloassoRegistrationSuccess(new HelloassoEvent($payment, $user));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Re-adhésion helloasso bien reçue !', $email);
        $this->assertAddress('To', $user->getEmail(), $email);
        $this->assertAddress('From', self::MEMBER_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, 'ta ré-adhésion d\'un montant de 10€');
    }

    public function testHelloassoPaymentMadeTooEarlyTellsWhenTheMembershipExpires(): void
    {
        $user = $this->aMemberUser(function (MembershipBuilder $builder) {
            $builder->registeredOn(new \DateTime('2030-01-15'));
        });
        $payment = $this->aPayment(10);

        $this->listener()->onHelloassoTooEarly(new HelloassoEvent($payment, $user));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Oups ! il et trop tôt pour ré-adhérer !', $email);
        $this->assertAddress('To', $user->getEmail(), $email);
        $this->assertEmailHtmlBodyContains($email, 'considérée comme un don');
        // registration (2030-01-15) + REGISTRATION_DURATION (1 year)
        $this->assertEmailHtmlBodyContains($email, '2031');
    }

    // ---------------------------------------------------------------------
    // shifts
    // ---------------------------------------------------------------------

    public function testBookingIsConfirmedToTheShifterAndCopiedToTheAdmins(): void
    {
        $shift = $this->aBookedShift('Cashier');

        $this->listener(true)->onShiftBooked(new ShiftBookedEvent($shift, false));

        $this->assertEmailCount(2);
        $confirmation = $this->getMailerMessage(0);
        $this->assertSubject('[ESPACE MEMBRES] Réservation de ton créneau confirmée', $confirmation);
        $this->assertAddress('To', $shift->getShifter()->getEmail(), $confirmation);
        $this->assertAddress('From', self::SHIFT_ADDRESS, $confirmation);
        $this->assertEmailHtmlBodyContains($confirmation, 'Cashier');

        $archive = $this->getMailerMessage(1);
        $this->assertSubject('[ESPACE MEMBRES] BOOKING', $archive);
        $this->assertAddress('To', self::SHIFT_ADDRESS, $archive);
        // the admins can answer the member straight from the archive mail
        $this->assertAddress('Reply-To', $shift->getShifter()->getEmail(), $archive);
        $this->assertEmailHtmlBodyContains($archive, 'Cashier');
    }

    public function testBookingIsOnlyConfirmedWhenTheAdminCopyIsOff(): void
    {
        $shift = $this->aBookedShift('Cashier');

        $this->listener(false)->onShiftBooked(new ShiftBookedEvent($shift, false));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Réservation de ton créneau confirmée', $email);
        $this->assertAddress('To', $shift->getShifter()->getEmail(), $email);
    }

    public function testFreedShiftIsNotifiedToItsFormerShifter(): void
    {
        $shift = $this->aBookedShift('Cashier');
        $beneficiary = $shift->getShifter();

        $this->listener()->onShiftFreed(new ShiftFreedEvent($shift, $beneficiary));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Créneau libéré', $email);
        $this->assertAddress('To', $beneficiary->getEmail(), $email);
        $this->assertAddress('From', self::SHIFT_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, 'Cashier');
        $this->assertEmailHtmlBodyContains($email, 'Bonjour Jane');
    }

    public function testDeletedShiftIsNotifiedToItsShifter(): void
    {
        $shift = $this->aBookedShift('Cashier');
        $beneficiary = $shift->getShifter();

        $this->listener()->onShiftDeleted(new ShiftDeletedEvent($shift, $beneficiary));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Créneau supprimé', $email);
        $this->assertAddress('To', $beneficiary->getEmail(), $email);
        $this->assertEmailHtmlBodyContains($email, 'Cashier');
    }

    public function testDeletedFreeShiftNotifiesNobody(): void
    {
        $shift = $this->aBookedShift('Cashier');

        $this->listener()->onShiftDeleted(new ShiftDeletedEvent($shift));

        $this->assertEmailCount(0);
    }

    public function testReminderRendersTheDynamicContentAsATemplate(): void
    {
        $this->setDynamicContent('SHIFT_REMINDER_EMAIL', 'See you soon, {{ beneficiary.firstname }}!');
        $shift = $this->aBookedShift('Cashier');

        $this->listener()->onShiftReminder(new ShiftReminderEvent($shift));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Ton créneau', $email);
        $this->assertAddress('To', $shift->getShifter()->getEmail(), $email);
        $this->assertEmailHtmlBodyContains($email, 'See you soon, Jane!');
        $this->assertEmailHtmlBodyContains($email, 'Cashier');
    }

    /**
     * I-BUG-4, reminder flavour: same unguarded findOneByCode()->getContent().
     */
    public function testReminderSurvivesAMissingDynamicContent(): void
    {
        $this->markTestIncomplete('I-BUG-4 open: EmailingEventListener reads SHIFT_REMINDER_EMAIL with findOneByCode()->getContent() and no null guard (Error on null).');

        $this->setDynamicContent('SHIFT_REMINDER_EMAIL', null);
        $shift = $this->aBookedShift('Cashier');

        $this->listener()->onShiftReminder(new ShiftReminderEvent($shift));

        $this->assertEmailCount(1);
    }

    public function testReservedShiftOffersTheFormerShifterToTakeItAgain(): void
    {
        $beneficiary = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $former = $this->aBookedShift('Cashier', $beneficiary, new \DateTime('2030-03-04 09:00'));
        $new = ShiftBuilder::aShift()->startingAt(new \DateTime('2030-04-01 09:00'))->forJob($former->getJob())->build();
        $new->setLastShifter($beneficiary);
        static::persist($new);

        $this->listener()->onShiftReserved(new ShiftReservedEvent($new, $former));

        $email = $this->theOnlyEmail();
        $this->assertStringStartsWith('[ESPACE MEMBRES] Reprends ton créneau du ', $email->getSubject());
        $this->assertAddress('To', $beneficiary->getEmail(), $email);
        $this->assertAddress('From', self::SHIFT_ADDRESS, $email);
        $token = $new->getTmpToken($beneficiary->getId());
        $this->assertEmailHtmlBodyContains($email, '/' . $new->getId() . '/accept?token=' . $token);
        $this->assertEmailHtmlBodyContains($email, '/' . $new->getId() . '/reject?token=' . $token);
    }

    public function testAlertsGoToTheRecipientsWithTheDefaultTemplate(): void
    {
        $this->setDynamicContent('LOT5_UNKNOWN_TEMPLATE', null);
        $alerts = [['bucket' => ['start' => new \DateTime('2030-03-04 09:00'), 'end' => new \DateTime('2030-03-04 12:00')], 'issue' => 'Only 1 shifter instead of 2']];

        $this->listener()->onShiftAlerts(new ShiftAlertsEvent($alerts, new \DateTime('2030-03-04'), 'LOT5_UNKNOWN_TEMPLATE', ['a@example.org', 'b@example.org']));

        $email = $this->theOnlyEmail();
        $this->assertStringStartsWith('[ALERTE CRENEAUX] ', $email->getSubject());
        $this->assertAddress('To', 'a@example.org', $email);
        $this->assertAddress('To', 'b@example.org', $email);
        $this->assertAddress('From', self::SHIFT_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, 'Only 1 shifter instead of 2');
        $this->assertEmailHtmlBodyContains($email, '9h00');
    }

    public function testAlertsUseTheDynamicTemplateWhenThereIsOne(): void
    {
        $this->setDynamicContent('LOT5_ALERTS_TEMPLATE', '{% for alert in alerts %}CUSTOM {{ alert.issue }}{% endfor %}');
        $alerts = [['bucket' => [], 'issue' => 'Only 1 shifter']];

        $this->listener()->onShiftAlerts(new ShiftAlertsEvent($alerts, new \DateTime('2030-03-04'), 'LOT5_ALERTS_TEMPLATE', ['a@example.org']));

        $this->assertEmailHtmlBodyContains($this->theOnlyEmail(), 'CUSTOM Only 1 shifter');
    }

    /**
     * @dataProvider silentAlertsProvider
     */
    public function testAlertsWithoutAlertsOrRecipientsSendNothing(array $alerts, ?array $recipients): void
    {
        $this->listener()->onShiftAlerts(new ShiftAlertsEvent($alerts, new \DateTime('2030-03-04'), null, $recipients));

        $this->assertEmailCount(0);
    }

    public function silentAlertsProvider(): array
    {
        return [
            'no alert' => [[], ['a@example.org']],
            'no recipient' => [[['bucket' => [], 'issue' => 'x']], null],
            'empty recipients' => [[['bucket' => [], 'issue' => 'x']], []],
        ];
    }

    // ---------------------------------------------------------------------
    // cycle
    // ---------------------------------------------------------------------

    public function testCycleStartAsksEveryBeneficiaryWhoStillHasToBookToBookTheirShifts(): void
    {
        $membership = $this->aMembershipWithTwoBeneficiaries();
        $date = new \DateTime('today');

        $this->listener()->onMemberCycleStart(new MemberCycleStartEvent($membership, $date, []));

        $this->assertEmailCount(2);
        $recipients = [];
        foreach ($this->getMailerMessages() as $email) {
            $this->assertSubject('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux', $email);
            $this->assertAddress('From', self::SHIFT_ADDRESS, $email);
            $this->assertEmailHtmlBodyContains($email, 'Votre nouveau cycle commence aujourd');
            $recipients[] = $email->getTo()[0]->getAddress();
        }
        $expected = [];
        foreach ($membership->getBeneficiaries() as $beneficiary) {
            $expected[] = $beneficiary->getEmail();
        }
        $this->assertEqualsCanonicalizing($expected, $recipients);
    }

    /**
     * @dataProvider silentCycleStartProvider
     */
    public function testCycleStartStaysSilent(callable $setUp, int $bookedMinutes): void
    {
        $builder = MembershipBuilder::aMembership()->withFirstShiftDate(new \DateTime('-6 months'));
        $setUp($builder);
        $membership = static::persist($builder->build());
        $shifts = [];
        if ($bookedMinutes) {
            $shifts[] = ShiftBuilder::aShift()->lasting($bookedMinutes)->bookedBy($membership->getMainBeneficiary())->build();
        }

        $this->listener()->onMemberCycleStart(new MemberCycleStartEvent($membership, new \DateTime('today'), $shifts));

        $this->assertEmailCount(0);
    }

    public function silentCycleStartProvider(): array
    {
        return [
            'frozen member' => [function (MembershipBuilder $b) {
                $b->frozen();
            }, 0],
            'fresh new member (first shift to come)' => [function (MembershipBuilder $b) {
                $b->withFirstShiftDate(new \DateTime('+1 month'));
            }, 0],
            'already booked the whole due time' => [function (MembershipBuilder $b) {}, 180],
        ];
    }

    public function testCycleStartStillMailsWhenTheBookedTimeIsBelowTheDueTime(): void
    {
        $membership = static::persist(MembershipBuilder::aMembership()->withFirstShiftDate(new \DateTime('-6 months'))->build());
        $shift = ShiftBuilder::aShift()->lasting(179)->bookedBy($membership->getMainBeneficiary())->build();

        $this->listener()->onMemberCycleStart(new MemberCycleStartEvent($membership, new \DateTime('today'), [$shift]));

        $this->assertEmailCount(1);
    }

    public function testCycleHalfRemindsTheMainBeneficiaryOnly(): void
    {
        $membership = $this->aMembershipWithTwoBeneficiaries();

        $this->listener()->onMemberCycleHalf(new MemberCycleHalfEvent($membership, new \DateTime('today'), []));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] déjà la moitié de ton cycle, un tour sur ton espace membre ?', $email);
        $this->assertAddress('To', $membership->getMainBeneficiary()->getEmail(), $email);
        $this->assertEmailHtmlBodyContains($email, 'Ton cycle a commencé il y a 14 jours');
    }

    public function testCycleHalfStaysSilentOnceTheDueTimeIsBooked(): void
    {
        $membership = static::persist(MembershipBuilder::aMembership()->withFirstShiftDate(new \DateTime('-6 months'))->build());
        $shift = ShiftBuilder::aShift()->lasting(180)->bookedBy($membership->getMainBeneficiary())->build();

        $this->listener()->onMemberCycleHalf(new MemberCycleHalfEvent($membership, new \DateTime('today'), [$shift]));

        $this->assertEmailCount(0);
    }

    // ---------------------------------------------------------------------
    // events and codes
    // ---------------------------------------------------------------------

    public function testProxyMailsTheOwnerAndTheGiverAndLetsThemAnswerEachOther(): void
    {
        $owner = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $giver = static::persist(MembershipBuilder::aMembership()->build());
        $proxy = $this->aProxy($owner, $giver);

        $this->listener()->onEventProxyCreated(new EventProxyCreatedEvent($proxy));

        $this->assertEmailCount(2);
        $toOwner = $this->getMailerMessage(0);
        $this->assertSubject('[General assembly] procuration', $toOwner);
        $this->assertAddress('To', $owner->getEmail(), $toOwner);
        $this->assertAddress('Reply-To', $giver->getMainBeneficiary()->getEmail(), $toOwner);
        $this->assertAddress('From', self::MEMBER_ADDRESS, $toOwner);
        $this->assertEmailHtmlBodyContains($toOwner, 'General assembly');

        $toGiver = $this->getMailerMessage(1);
        $this->assertSubject('[General assembly] ta procuration', $toGiver);
        $this->assertAddress('To', $giver->getMainBeneficiary()->getEmail(), $toGiver);
        $this->assertAddress('Reply-To', $owner->getEmail(), $toGiver);
        $this->assertEmailHtmlBodyContains($toGiver, 'General assembly');
    }

    public function testNewKeyBoxCodeIsSentToItsRegistrarWithTheCurrentOne(): void
    {
        $registrar = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $old = (new Code())->setValue('1234')->setRegistrar($registrar->getUser())->setClosed(false);
        $new = (new Code())->setValue('5678')->setRegistrar($registrar->getUser())->setClosed(false);
        static::persist($old, $new);

        $this->listener()->onCodeNew(new CodeNewEvent($new, [$old]));

        $email = $this->theOnlyEmail();
        $this->assertSubject('[ESPACE MEMBRES] Nouveau code boîtier clefs', $email);
        $this->assertAddress('To', $registrar->getEmail(), $email);
        $this->assertAddress('From', self::SHIFT_ADDRESS, $email);
        $this->assertEmailHtmlBodyContains($email, '<strong>1234</strong>');
        $this->assertEmailHtmlBodyContains($email, '<strong>5678</strong>');
        $this->assertEmailHtmlBodyContains($email, '/codes/close_all?token=');
    }

    /**
     * onAnonymousBeneficiaryRecall() and onCodeNew() (and the helloasso
     * listener) fetch the helper with $this->container->get(SwipeCard::class),
     * but the service is private: the compiled container inlines it and get()
     * throws ServiceNotFoundException. Tracked as MAIL-SWIPECARD-DI in TODO-PRIORISEE.md.
     */
    public function testSwipeCardHelperIsReachableFromTheContainer(): void
    {
        $this->markTestIncomplete('MAIL-SWIPECARD-DI open: App\Helper\SwipeCard is private, EmailingEventListener::onAnonymousBeneficiaryRecall()/onCodeNew() get() it from the container and throw.');

        $this->assertTrue(static::$kernel->getContainer()->has(SwipeCard::class));
    }

    // ---------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------

    /**
     * The listener is given the application container. It looks the SwipeCard
     * helper up there, which the compiled container does not expose (see
     * testSwipeCardHelperIsReachableFromTheContainer): the tests hand it over
     * through a thin wrapper, to check the mails themselves.
     */
    private function listener(bool $copyToAdmin = true): EmailingEventListener
    {
        $real = static::$kernel->getContainer();
        $swipeCard = static::$container->get(SwipeCard::class);
        $container = new class ($real, $swipeCard) extends Container {
            private $real;
            private $swipeCard;

            public function __construct(Container $real, SwipeCard $swipeCard)
            {
                parent::__construct();
                $this->real = $real;
                $this->swipeCard = $swipeCard;
            }

            public function get($id, $invalidBehavior = 1)
            {
                return SwipeCard::class === $id ? $this->swipeCard : $this->real->get($id, $invalidBehavior);
            }

            public function has($id)
            {
                return SwipeCard::class === $id || $this->real->has($id);
            }

            public function getParameter($name)
            {
                return $this->real->getParameter($name);
            }
        };

        return new EmailingEventListener(
            static::entityManager(),
            new Logger('test', [new NullHandler()]),
            $container,
            static::$container->get('mailer.mailer'),
            $copyToAdmin,
            static::$container->get(SwipeCard::class)
        );
    }

    private function theOnlyEmail(): Email
    {
        $this->assertEmailCount(1);

        return $this->getMailerMessage(0);
    }

    private function assertSubject(string $expected, Email $email): void
    {
        $this->assertSame($expected, $email->getSubject());
    }

    private function assertAddress(string $header, string $expected, Email $email): void
    {
        $this->assertEmailAddressContains($email, $header, $expected);
    }

    /**
     * Sets (or, with null, removes) the content of a dynamic content row.
     */
    private function setDynamicContent(string $code, ?string $content): void
    {
        $em = static::entityManager();
        $row = $em->getRepository(DynamicContent::class)->findOneBy(['code' => $code]);

        if (null === $content) {
            if ($row) {
                $em->remove($row);
                $em->flush();
            }

            return;
        }

        if (!$row) {
            $row = new DynamicContent();
            $row->setCode($code);
            $row->setName($code);
            $row->setDescription($code);
            $row->setType('general');
        }
        $row->setContent($content);
        static::persist($row);
    }

    private function anAnonymousBeneficiary(string $email): AnonymousBeneficiary
    {
        $anonymous = new AnonymousBeneficiary();
        $anonymous->setEmail($email);

        return static::persist($anonymous);
    }

    private function aMemberUser(?callable $customize = null)
    {
        $builder = MembershipBuilder::aMembership();
        if ($customize) {
            $customize($builder);
        }

        return static::persist($builder->build())->getMainBeneficiary()->getUser();
    }

    private function aPayment(float $amount): HelloassoPayment
    {
        $payment = new HelloassoPayment();
        $payment->setAmount($amount);
        $payment->setPayerFirstName('Jane');
        $payment->setPayerLastName('Doe');

        return $payment;
    }

    private function aBookedShift(string $jobName, ?Beneficiary $beneficiary = null, ?\DateTime $start = null): Shift
    {
        $beneficiary ??= static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()
            ->startingAt($start ?? new \DateTime('2030-03-04 09:30'))
            ->bookedBy($beneficiary)
            ->build()
        ;
        $shift->getJob()->setName($jobName . ' ' . $shift->getJob()->getName());
        static::persist($shift->getJob(), $shift);

        return $shift;
    }

    private function aMembershipWithTwoBeneficiaries(): Membership
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withFirstShiftDate(new \DateTime('-6 months'))
                ->withBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Bob', 'Partner'))
                ->build()
        );
    }

    private function aProxy(Beneficiary $owner, Membership $giver): Proxy
    {
        $event = new Event();
        $event->setTitle('General assembly');
        $event->setDate(new \DateTime('2030-06-01 18:00'));

        $proxy = new Proxy();
        $proxy->setEvent($event);
        $proxy->setOwner($owner);
        $proxy->setGiver($giver);

        return $proxy;
    }
}
