<?php

namespace App\Enums;

enum CustomerReviewRating: string
{
    case VerySatisfied = 'very_satisfied';
    case Satisfied = 'satisfied';
    case Unsatisfied = 'unsatisfied';

    public function label(): string
    {
        return match ($this) {
            self::VerySatisfied => 'Très satisfait',
            self::Satisfied => 'Satisfait',
            self::Unsatisfied => 'Insatisfait',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::VerySatisfied => 'emerald',
            self::Satisfied => 'amber',
            self::Unsatisfied => 'rose',
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
}
