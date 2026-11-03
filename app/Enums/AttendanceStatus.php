<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case OnBreak = 'on_break';
    case Left = 'left';
    case Worked = 'worked';
    case Incomplete = 'incomplete';
    case Expected = 'expected';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case DayOff = 'day_off';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Au travail',
            self::OnBreak => 'En pause',
            self::Left => 'Parti',
            self::Worked => 'Présent',
            self::Incomplete => 'Départ non pointé',
            self::Expected => 'Attendu',
            self::Absent => 'Absent',
            self::OnLeave => 'En congé',
            self::DayOff => 'Repos',
            self::Upcoming => 'À venir',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present, self::Worked => 'emerald',
            self::OnBreak, self::Incomplete => 'amber',
            self::Absent => 'rose',
            self::OnLeave => 'sky',
            self::Left => 'stone',
            self::Expected, self::DayOff, self::Upcoming => 'neutral',
        };
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'tone' => $this->tone(),
        ];
    }

    public function isOnSite(): bool
    {
        return $this === self::Present || $this === self::OnBreak;
    }
}
