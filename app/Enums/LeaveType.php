<?php

namespace App\Enums;

enum LeaveType: string
{
    case PaidLeave = 'paid_leave';
    case Sick = 'sick';
    case Permission = 'permission';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PaidLeave => 'Congé',
            self::Sick => 'Maladie',
            self::Permission => 'Permission',
            self::Other => 'Autre absence',
        };
    }
}
