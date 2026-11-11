<?php

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shop
 */
class ShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'isActive' => $this->is_active,
            'staffCount' => $this->whenCounted('staff'),
            'kioskCount' => $this->whenCounted('kiosks'),
            'managers' => $this->whenLoaded('managers', fn (): array => $this->managers
                ->map(fn ($manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'initials' => $manager->initials(),
                    'isActive' => $manager->is_active,
                ])
                ->values()
                ->all()),
            'departments' => $this->whenLoaded('departments', fn (): array => $this->departments
                ->map(fn ($department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'staffCount' => (int) $department->users_count,
                ])
                ->values()
                ->all()),
            'kiosks' => $this->whenLoaded('kiosks', fn (): array => $this->kiosks
                ->map(fn ($kiosk): array => [
                    'id' => $kiosk->public_id,
                    'name' => $kiosk->name,
                    'url' => route('kiosk.show', $kiosk),
                    'isActive' => $kiosk->is_active,
                ])
                ->values()
                ->all()),
        ];
    }
}
