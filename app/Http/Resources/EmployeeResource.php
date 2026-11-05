<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials(),
            'phone' => $this->phone,
            'email' => $this->email,
            'shopId' => $this->shop_id,
            'shop' => $this->whenLoaded('shop', fn (): ?string => $this->shop?->name),
            'departmentId' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (): ?string => $this->department?->name),
            'isActive' => $this->is_active,
            'mustChangePassword' => (bool) $this->must_change_password,
            'workScheduleCount' => $this->whenCounted('workSchedules'),
            'schedules' => $this->whenLoaded('workSchedules', fn (): array => $this->workSchedules
                ->sortBy('weekday')
                ->map(fn ($schedule): array => [
                    'weekday' => $schedule->weekday,
                    'starts_at' => $schedule->formattedStart(),
                    'ends_at' => $schedule->formattedEnd(),
                ])
                ->values()
                ->all()),
        ];
    }
}
