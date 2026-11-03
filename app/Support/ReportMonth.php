<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportMonth
{
    public function __construct(public CarbonImmutable $month) {}

    public static function fromRequest(Request $request): self
    {
        $value = $request->string('mois')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return new self(CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth());
        }

        return new self(CarbonImmutable::now()->startOfMonth());
    }

    /**
     * @return array{value: string, label: string, previous: string, next: ?string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value(),
            'label' => $this->label(),
            'previous' => $this->previous(),
            'next' => $this->next(),
        ];
    }

    public function start(): CarbonImmutable
    {
        return $this->month->startOfMonth();
    }

    public function end(): CarbonImmutable
    {
        return $this->month->endOfMonth()->startOfDay();
    }

    public function value(): string
    {
        return $this->month->format('Y-m');
    }

    public function label(): string
    {
        return ucfirst($this->month->translatedFormat('F Y'));
    }

    public function previous(): string
    {
        return $this->month->subMonthNoOverflow()->format('Y-m');
    }

    public function next(): ?string
    {
        $next = $this->month->addMonthNoOverflow();

        return $next->isAfter(CarbonImmutable::now()->startOfMonth()) ? null : $next->format('Y-m');
    }
}
