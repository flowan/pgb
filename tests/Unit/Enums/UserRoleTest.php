<?php

namespace Tests\Unit\Enums;

use App\Enums\CaregiverType;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    public function test_user_role_has_budget_holder_case(): void
    {
        $this->assertSame('budget_holder', UserRole::BudgetHolder->value);
    }

    public function test_user_role_has_caregiver_case(): void
    {
        $this->assertSame('caregiver', UserRole::Caregiver->value);
    }

    public function test_user_role_has_exactly_two_cases(): void
    {
        $this->assertCount(2, UserRole::cases());
    }

    public function test_user_role_can_be_created_from_value(): void
    {
        $this->assertSame(UserRole::BudgetHolder, UserRole::from('budget_holder'));
        $this->assertSame(UserRole::Caregiver, UserRole::from('caregiver'));
    }

    public function test_caregiver_type_has_all_cases(): void
    {
        $this->assertSame('parent', CaregiverType::Parent->value);
        $this->assertSame('care_worker', CaregiverType::CareWorker->value);
        $this->assertSame('day_care', CaregiverType::DayCare->value);
        $this->assertSame('zzp', CaregiverType::Zzp->value);
        $this->assertSame('other', CaregiverType::Other->value);
    }

    public function test_caregiver_type_has_exactly_five_cases(): void
    {
        $this->assertCount(5, CaregiverType::cases());
    }

    public function test_caregiver_type_can_be_created_from_value(): void
    {
        $this->assertSame(CaregiverType::Parent, CaregiverType::from('parent'));
        $this->assertSame(CaregiverType::CareWorker, CaregiverType::from('care_worker'));
        $this->assertSame(CaregiverType::DayCare, CaregiverType::from('day_care'));
        $this->assertSame(CaregiverType::Zzp, CaregiverType::from('zzp'));
        $this->assertSame(CaregiverType::Other, CaregiverType::from('other'));
    }

    public function test_schedule_exception_type_has_all_cases(): void
    {
        $this->assertSame('cancelled', ScheduleExceptionType::Cancelled->value);
        $this->assertSame('modified', ScheduleExceptionType::Modified->value);
        $this->assertSame('added', ScheduleExceptionType::Added->value);
    }

    public function test_schedule_exception_type_has_exactly_three_cases(): void
    {
        $this->assertCount(3, ScheduleExceptionType::cases());
    }

    public function test_schedule_exception_type_can_be_created_from_value(): void
    {
        $this->assertSame(ScheduleExceptionType::Cancelled, ScheduleExceptionType::from('cancelled'));
        $this->assertSame(ScheduleExceptionType::Modified, ScheduleExceptionType::from('modified'));
        $this->assertSame(ScheduleExceptionType::Added, ScheduleExceptionType::from('added'));
    }
}
