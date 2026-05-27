<?php

namespace App\Enums;

enum UserRole: string
{
    case BudgetHolder = 'budget_holder';
    case Caregiver = 'caregiver';
}
