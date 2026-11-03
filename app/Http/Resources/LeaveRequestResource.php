<?php

namespace App\Http\Resources;

use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'status' => $this->status->toArray(),
            'startsOn' => $this->starts_on->toDateString(),
            'endsOn' => $this->ends_on->toDateString(),
            'startsLabel' => $this->starts_on->translatedFormat('j M Y'),
            'endsLabel' => $this->ends_on->translatedFormat('j M Y'),
            'shortPeriod' => $this->starts_on->format('d/m').' au '.$this->ends_on->format('d/m'),
            'dayCount' => $this->dayCount(),
            'startDay' => $this->starts_on->format('d'),
            'startMonth' => $this->starts_on->translatedFormat('M'),
            'timing' => $this->timingLabel(),
            'submittedAt' => $this->created_at?->translatedFormat('j M Y'),
            'reason' => $this->reason,
            'reviewNote' => $this->review_note,
            'reviewedAt' => $this->reviewed_at?->format('d/m/Y à H:i'),
            'reviewer' => $this->whenLoaded('reviewer', fn (): ?string => $this->reviewer?->name),
            'employee' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'initials' => $this->user->initials(),
            ]),
        ];
    }

    private function timingLabel(): string
    {
        $today = CarbonImmutable::today();

        if ($this->ends_on->lessThan($today)) {
            return 'Terminé';
        }

        if ($this->starts_on->lessThanOrEqualTo($today)) {
            return 'En cours';
        }

        $days = (int) $today->diffInDays($this->starts_on);

        return $days === 1 ? 'Demain' : "Dans {$days} jours";
    }
}
