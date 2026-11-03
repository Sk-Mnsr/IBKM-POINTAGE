<?php

namespace App\Http\Resources;

use App\Enums\PunchType;
use App\Models\Punch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Punch
 */
class PunchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->toArray(),
            'date' => $this->punched_at->toDateString(),
            'dateLabel' => ucfirst($this->punched_at->translatedFormat('D d/m/Y')),
            'dayLabel' => ucfirst($this->punched_at->translatedFormat('l j F Y')),
            'time' => $this->punched_at->format('H:i'),
            'timeWithSeconds' => $this->punched_at->format('H:i:s'),
            'month' => $this->punched_at->format('Y-m'),
            'employee' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'initials' => $this->user->initials(),
                'department' => $this->user->relationLoaded('department') ? $this->user->department?->name : null,
            ]),
            'kiosk' => $this->whenLoaded('kiosk', fn (): string => $this->kiosk->name ?? 'Saisie du gérant'),
            'isOffline' => $this->is_offline,
            'isManual' => $this->is_manual,
            'isCorrected' => $this->whenCounted('corrections', fn (int $count): bool => $count > 0),
            'syncedAt' => $this->synced_at?->format('d/m à H:i'),
            'lateMinutes' => $this->when(
                $this->type === PunchType::Arrival && $this->relationLoaded('user') && $this->user->relationLoaded('workSchedules'),
                fn (): int => $this->user->workSchedules
                    ->firstWhere('weekday', $this->punched_at->dayOfWeekIso)
                    ?->lateMinutesFor($this->punched_at) ?? 0,
            ),
        ];
    }
}
