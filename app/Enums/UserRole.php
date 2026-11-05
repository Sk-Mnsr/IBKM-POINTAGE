<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Admin => 'Gérant',
            self::Staff => 'Personnel',
        };
    }

    public function isManager(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }
}
