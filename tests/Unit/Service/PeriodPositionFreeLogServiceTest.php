<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Job;
use App\Entity\Period;
use App\Entity\PeriodPosition;
use App\Entity\User;
use App\Service\PeriodPositionFreeLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
class PeriodPositionFreeLogServiceTest extends TestCase
{
    /** @var RequestStack */
    private $requestStack;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var PeriodPositionFreeLogService */
    private $service;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->tokenStorage = new TokenStorage();
        $this->service = new PeriodPositionFreeLogService(
            $this->createMock(EntityManagerInterface::class),
            $this->requestStack,
            $this->tokenStorage
        );
    }

    private function aPosition($weekCycle = null): PeriodPosition
    {
        $job = new Job();
        $job->setName('Caisse');

        $period = new Period();
        $period->setJob($job);
        $period->setDayOfWeek(2);
        $period->setStart(new \DateTime('1970-01-01 09:30'));
        $period->setEnd(new \DateTime('1970-01-01 12:00'));

        $position = new PeriodPosition();
        $position->setPeriod($period);
        if (null !== $weekCycle) {
            $position->setWeekCycle($weekCycle);
        }

        return $position;
    }

    private function enterRequest(?string $route): void
    {
        $this->requestStack->push(new Request([], [], null === $route ? [] : ['_route' => $route]));
    }

    public function testGeneratePeriodPositionStringIsTheStringForm(): void
    {
        $position = $this->aPosition('B');

        $string = $this->service->generatePeriodPositionString($position);

        $this->assertSame((string) $position, $string);
        $this->assertStringContainsString('Caisse', $string);
        $this->assertStringContainsString('9h30 à 12h00', $string);
        $this->assertStringContainsString('Semaine B', $string);
    }

    public function testInitPeriodPositionFreeLogCopiesThePositionTheBeneficiaryAndTheBookedTime(): void
    {
        $this->enterRequest('period_position_free');
        $position = $this->aPosition();
        $beneficiary = new Beneficiary();
        $bookedTime = new \DateTime('2030-01-02 10:00');

        $log = $this->service->initPeriodPositionFreeLog($position, $beneficiary, $bookedTime);

        $this->assertSame($position, $log->getPeriodPosition());
        $this->assertSame($beneficiary, $log->getBeneficiary());
        $this->assertSame((string) $position, $log->getPeriodPositionString());
        $this->assertSame($bookedTime, $log->getBookedTime());
        $this->assertSame('period_position_free', $log->getRequestRoute());
    }

    public function testInitPeriodPositionFreeLogWithoutBookedTimeLeavesItEmpty(): void
    {
        $this->enterRequest('period_position_free');

        $log = $this->service->initPeriodPositionFreeLog($this->aPosition(), new Beneficiary());

        $this->assertNull($log->getBookedTime());
    }

    public function testInitPeriodPositionFreeLogRecordsTheAuthenticatedUser(): void
    {
        $this->enterRequest('period_position_free');
        $user = new User();
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, null, 'main', ['ROLE_USER']));

        $log = $this->service->initPeriodPositionFreeLog($this->aPosition(), new Beneficiary());

        $this->assertSame($user, $log->getCreatedBy());
    }

    public function testInitPeriodPositionFreeLogWithoutTokenHasNoAuthor(): void
    {
        $this->enterRequest('period_position_free');

        $this->assertNull($this->service->initPeriodPositionFreeLog($this->aPosition(), new Beneficiary())->getCreatedBy());
    }

    public function testInitPeriodPositionFreeLogForAnonymousTokenHasNoAuthor(): void
    {
        $this->enterRequest('period_position_free');
        $this->tokenStorage->setToken(new UsernamePasswordToken('anon.', null, 'main'));

        $this->assertNull($this->service->initPeriodPositionFreeLog($this->aPosition(), new Beneficiary())->getCreatedBy());
    }

    /**
     * I-BUG-9, same defect as ShiftFreeLogService: no current request outside
     * HTTP, and the service dereferences it unguarded.
     */
    public function testInitPeriodPositionFreeLogOutsideAnHttpRequest(): void
    {
        $this->markTestIncomplete('I-BUG-9 open: PeriodPositionFreeLogService::initPeriodPositionFreeLog() calls get() on a null request outside HTTP; the log should have a null request route.');

        $log = $this->service->initPeriodPositionFreeLog($this->aPosition(), new Beneficiary());

        $this->assertNull($log->getRequestRoute());
    }
}
