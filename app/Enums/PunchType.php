<?php

namespace App\Enums;

enum PunchType: string
{
    case Arrival = 'arrival';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case Departure = 'departure';

    public function label(): string
    {
        return match ($this) {
            self::Arrival => 'Arrivée',
            self::BreakStart => 'Début de pause',
            self::BreakEnd => 'Fin de pause',
            self::Departure => 'Départ',
        };
    }

    public function buttonLabel(): string
    {
        return match ($this) {
            self::Arrival => 'Valider mon arrivée',
            self::BreakStart => 'Partir en pause',
            self::BreakEnd => 'Reprendre le travail',
            self::Departure => 'Terminer ma journée',
        };
    }

    /**
     * @return array{value: string, label: string, buttonLabel: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'buttonLabel' => $this->buttonLabel(),
        ];
    }

    public function confirmation(string $givenName): string
    {
        return match ($this) {
            self::Arrival => "Bonjour {$givenName}",
            self::BreakStart => "Bonne pause {$givenName}",
            self::BreakEnd => "Bon retour {$givenName}",
            self::Departure => "Au revoir {$givenName}",
        };
    }
}
