<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Repository\ShiftRepository;
use AppBundle\Service\BeneficiaryService;
use AppBundle\Service\MembershipService;
use AppBundle\Service\ShiftService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use \Datetime;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShiftServiceTest extends TestCase
{
    /**
     * @var ShiftService
     */
    protected $shiftService;

    private $em;

    private $container;

    // Membership parameters
    private $registration_duration = '1 year';
    private $registration_every_civil_year = true;
    private $cycle_type = 'abcd';
    // Shift parameters
    private $due_duration_by_cycle = 180;
    private $min_shift_duration = 90;
    private $new_users_start_as_beginner = false;
    private $allow_extra_shifts = false;
    private $max_time_in_advance_to_book_extra_shifts = '3 days';
    private $forbid_shift_overlap_time = 30;
    private $use_fly_and_fixed = false;
    private $fly_and_fixed_allow_fixed_shift_free = false;
    private $use_time_log_saving = false;
    private $time_log_saving_shift_free_min_time_in_advance_days = 3;
    private $time_log_saving_shift_free_allow_only_if_enough_saving = false;

    public function setUp(): void
    {

        // Mock the ContainerInterface
        $this->container = $this
            ->getMockBuilder(ContainerInterface::class)
            ->getMock();

        // set parameters for the container
        $this->container->method('getParameter')
            ->will($this->returnCallback(function ($parameter) {
                switch ($parameter) {
                    case 'registration_duration':
                        return $this->registration_duration;
                    case 'registration_every_civil_year':
                        return $this->registration_every_civil_year;
                    case 'cycle_type':
                        return $this->cycle_type;
                    case 'due_duration_by_cycle':
                        return $this->due_duration_by_cycle;
                    case 'min_shift_duration':
                        return $this->min_shift_duration;
                    case 'new_users_start_as_beginner':
                        return $this->new_users_start_as_beginner;
                    case 'allow_extra_shifts':
                        return $this->allow_extra_shifts;
                    case 'max_time_in_advance_to_book_extra_shifts':
                        return $this->max_time_in_advance_to_book_extra_shifts;
                    case 'forbid_shift_overlap_time':
                        return $this->forbid_shift_overlap_time;
                    case 'use_fly_and_fixed':
                        return $this->use_fly_and_fixed;
                    case 'fly_and_fixed_allow_fixed_shift_free':
                        return $this->fly_and_fixed_allow_fixed_shift_free;
                    case 'use_time_log_saving':
                        return $this->use_time_log_saving;
                    case 'time_log_saving_shift_free_min_time_in_advance_days':
                        return $this->time_log_saving_shift_free_min_time_in_advance_days;
                    case 'time_log_saving_shift_free_allow_only_if_enough_saving':
                        return $this->time_log_saving_shift_free_allow_only_if_enough_saving;
                    default:
                        return null;
                }
            }));

        // Mock the EntityManager
        $this->em = $this
            ->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock the shift repository
        $shiftRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['findShiftsForBeneficiary'])
            ->getMock();

        // Define the behavior of the findShiftsForBeneficiary() method
        $shiftRepositoryMock->method('findShiftsForBeneficiary')
            ->willReturn(
                new ArrayCollection()
            );

        // Mock the getRepository() method of the EntityManager
        $this->em->expects($this->any())
            ->method('getRepository')
            ->with('AppBundle:Shift')
            ->willReturn($shiftRepositoryMock);

        $membershipService = new MembershipService($this->container, $this->em);
        $beneficiaryService = new BeneficiaryService($this->container, $this->em, $membershipService);
        $this->shiftService = new ShiftService(
            $this->em, 
            $beneficiaryService, 
            $membershipService, 
            $this->due_duration_by_cycle, 
            $this->min_shift_duration, 
            $this->new_users_start_as_beginner, 
            $this->allow_extra_shifts, 
            $this->max_time_in_advance_to_book_extra_shifts, 
            $this->forbid_shift_overlap_time,
            $this->use_fly_and_fixed,
            $this->fly_and_fixed_allow_fixed_shift_free,
            $this->use_time_log_saving,
            $this->time_log_saving_shift_free_min_time_in_advance_days,
            $this->time_log_saving_shift_free_allow_only_if_enough_saving 
        );
    }
    /*
     * Test if the beneficiary can book a shift on the first cycle when no flying
     */
    public function testShiftTimeByCycle()
    {
        $member = new Membership();
        $beneficiary = new Beneficiary();
        $beneficiary->setFlying(false);
        $member->setMainBeneficiary($beneficiary);

        $this->assertTrue($this->shiftService->canBookOnCycle($beneficiary, 0));
    }

    /**
     * Call to isShiftBookable for an empty shift and a user with correct rights
     */
    public function testIsShiftBookableWithEmptyShiftAndBeginner()
    {
        $this->assertFalse($this->doIsShiftBookableTest(true, true));
    }

    /**
     * Call to isShiftBookable for an empty shift and a user without rights (eg : a beginner)
     */
    public function testIsShiftBookableWithEmptyShiftAndNotABeginner()
    {
        $this->assertTrue($this->doIsShiftBookableTest(false, true));
    }

    /**
     * Call to isShiftBookable for a non-empty shift and a user without rights to book an empty shift
     * It should return true because it's not an empty shift
     */
    public function testIsShiftBookableWithNotEmptyShiftAndBeginner()
    {
        $this->assertTrue($this->doIsShiftBookableTest(true, false));
    }

    /**
     * @param $beginner
     * @param $emptyShift boolean
     * @return mixed
     */
    private function doIsShiftBookableTest($beginner, bool $emptyShift)
    {
        $beneficiary = new Beneficiary();
        $beneficiary->setFlying(false);
        $member = new Membership();
        $member->setMainBeneficiary($beneficiary);
        $user = new User();
        $beneficiary->setUser($user);

        $shift = $this
            ->getMockBuilder(Shift::class)
            ->getMock();
        $shift->method('getStart')
             ->willReturn(new Datetime());
        $shift->expects($this->any())
            ->method('getIsPast')
            ->will($this->returnValue(false));
        $membershipService = new MembershipService($this->container, $this->em);
        $beneficiaryService = new BeneficiaryService($this->container, $this->em, $membershipService);
        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['isShiftEmpty', 'canBookDuration', 'isBeginner'])
            ->setConstructorArgs(
                [
                    $this->em,
                    $beneficiaryService,
                    $membershipService,
                    $this->due_duration_by_cycle,
                    $this->min_shift_duration,
                    $this->new_users_start_as_beginner,
                    $this->allow_extra_shifts,
                    $this->max_time_in_advance_to_book_extra_shifts,
                    $this->forbid_shift_overlap_time,
                    $this->use_fly_and_fixed,
                    $this->fly_and_fixed_allow_fixed_shift_free,
                    $this->use_time_log_saving,
                    $this->time_log_saving_shift_free_min_time_in_advance_days,
                    $this->time_log_saving_shift_free_allow_only_if_enough_saving
                ]
            )
            ->getMock();
        $shiftService->expects($this->any())
            ->method('isShiftEmpty')
            ->willReturn($emptyShift);
        $shiftService->expects($this->any())
            ->method('canBookDuration')
            ->willReturn(true);
        $shiftService->expects($this->any())
            ->method('isBeginner')
            ->willReturn($beginner);

        return $shiftService->isShiftBookable($shift, $beneficiary);
    }

    public function testIsBeginnerNewUsersBeginnerWithNotABeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(false, true));
    }

    public function testIsBeginnerNewUsersBeginnerWithBeginner()
    {
        $this->assertTrue($this->doTestIsBeginner(true, true));
    }

    public function testIsBeginnerNewUsersNotBeginnerWithNotABeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(false, false));
    }

    public function testIsBeginnerNewUsersNotBeginnerWithBeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(true, false));
    }

    private function doTestIsBeginner($beginner, $newUserStartAsBeginner)
    {
        $beneficiary = new Beneficiary();
        $beneficiary->setFlying(false);

        $membershipService = new MembershipService($this->container, $this->em);
        $beneficiaryService = new BeneficiaryService($this->container, $this->em, $membershipService);
        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['hasPreviousValidShifts'])
            ->setConstructorArgs([
                $this->em,
                $beneficiaryService,
                $membershipService,
                $this->due_duration_by_cycle,
                $this->min_shift_duration,
                $newUserStartAsBeginner,
                $this->allow_extra_shifts,
                $this->max_time_in_advance_to_book_extra_shifts,
                $this->forbid_shift_overlap_time,
                $this->use_fly_and_fixed,
                $this->fly_and_fixed_allow_fixed_shift_free,
                $this->use_time_log_saving,
                $this->time_log_saving_shift_free_min_time_in_advance_days,
                $this->time_log_saving_shift_free_allow_only_if_enough_saving
            ])
            ->getMock()
        ;

        $shiftService->expects($this->any())
            ->method('hasPreviousValidShifts')
            ->willReturn(!$beginner);

        return $shiftService->isBeginner($beneficiary);
    }

    public function testHasPreviousValidShiftsWithShift()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P10D'));
        $this->assertTrue($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithShiftInTheFuture()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P10D'));
        $this->assertFalse($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithDismissedShift()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P10D'));
        $this->assertFalse($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithoutShift()
    {
        $this->assertFalse($this->doTestHasPreviousValidShifts(null));
    }

    public function doTestHasPreviousValidShifts($shiftDate)
    {
        $shifts = new ArrayCollection();

        if ($shiftDate)
        {
            $shift = new Shift();
            $shift->setStart($shiftDate);
            $shifts->add($shift);
        }

        $beneficiary = $this->getMockBuilder(Beneficiary::class)->getMock();
        $beneficiary->expects($this->any())
            ->method('getShifts')
            ->willReturn($shifts);

        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethodsExcept(['hasPreviousValidShifts'])
            ->disableOriginalConstructor()
            ->getMock();

        return $shiftService->hasPreviousValidShifts($beneficiary);
    }
}
