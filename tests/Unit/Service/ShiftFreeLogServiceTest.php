<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\User;
use App\Service\ShiftFreeLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
class ShiftFreeLogServiceTest extends TestCase
{
    /** @var RequestStack */
    private $requestStack;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var ShiftFreeLogService */
    private $service;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->tokenStorage = new TokenStorage();
        $this->service = new ShiftFreeLogService(
            $this->createMock(EntityManagerInterface::class),
            $this->requestStack,
            $this->tokenStorage
        );
    }

    private function aShift(): Shift
    {
        $job = new Job();
        $job->setName('Caisse');

        $shift = new Shift();
        $shift->setJob($job);
        $shift->setStart(new \DateTime('2030-03-04 09:30'));
        $shift->setEnd(new \DateTime('2030-03-04 12:00'));

        return $shift;
    }

    private function enterRequest(?string $route): void
    {
        $this->requestStack->push(new Request([], [], null === $route ? [] : ['_route' => $route]));
    }

    public function testGenerateShiftStringNamesTheJobAndTheSlot(): void
    {
        $this->assertSame('Caisse - 04/03/2030 - 9h30 à 12h00', $this->service->generateShiftString($this->aShift()));
    }

    public function testInitShiftFreeLogCopiesTheShiftTheBeneficiaryAndTheReason(): void
    {
        $this->enterRequest('shift_free');
        $shift = $this->aShift();
        $beneficiary = new Beneficiary();

        $log = $this->service->initShiftFreeLog($shift, $beneficiary, true, 'sick');

        $this->assertSame($shift, $log->getShift());
        $this->assertSame($beneficiary, $log->getBeneficiary());
        $this->assertSame('Caisse - 04/03/2030 - 9h30 à 12h00', $log->getShiftString());
        $this->assertTrue($log->isFixe());
        $this->assertSame('sick', $log->getReason());
        $this->assertSame('shift_free', $log->getRequestRoute());
    }

    public function testInitShiftFreeLogWithoutReasonLeavesItEmpty(): void
    {
        $this->enterRequest('shift_free');

        $log = $this->service->initShiftFreeLog($this->aShift(), new Beneficiary());

        $this->assertFalse($log->isFixe());
        $this->assertNull($log->getReason());
    }

    public function testInitShiftFreeLogRecordsTheAuthenticatedUser(): void
    {
        $this->enterRequest('shift_free');
        $user = new User();
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, null, 'main', ['ROLE_USER']));

        $log = $this->service->initShiftFreeLog($this->aShift(), new Beneficiary());

        $this->assertSame($user, $log->getCreatedBy());
    }

    public function testInitShiftFreeLogWithoutTokenHasNoAuthor(): void
    {
        $this->enterRequest('shift_free');

        $this->assertNull($this->service->initShiftFreeLog($this->aShift(), new Beneficiary())->getCreatedBy());
    }

    public function testInitShiftFreeLogForAnonymousTokenHasNoAuthor(): void
    {
        $this->enterRequest('shift_free');
        // an anonymous token's "user" is the string "anon.", not an object
        $this->tokenStorage->setToken(new UsernamePasswordToken('anon.', null, 'main'));

        $this->assertNull($this->service->initShiftFreeLog($this->aShift(), new Beneficiary())->getCreatedBy());
    }

    public function testInitShiftFreeLogKeepsAnEmptyRouteWhenTheRequestHasNone(): void
    {
        $this->enterRequest(null);

        $this->assertNull($this->service->initShiftFreeLog($this->aShift(), new Beneficiary())->getRequestRoute());
    }

    /**
     * I-BUG-9: outside an HTTP request (console command, cron) there is no
     * current request, and the service dereferences it unguarded. The log
     * should simply carry no route.
     */
    public function testInitShiftFreeLogOutsideAnHttpRequest(): void
    {
        $this->markTestIncomplete('I-BUG-9 open: ShiftFreeLogService::initShiftFreeLog() calls get() on a null request outside HTTP; the log should have a null request route.');

        $log = $this->service->initShiftFreeLog($this->aShift(), new Beneficiary());

        $this->assertNull($log->getRequestRoute());
    }
}
