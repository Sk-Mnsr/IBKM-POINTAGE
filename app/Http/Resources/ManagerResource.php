<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ManagerResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'roleLabel' => $this->role->label(),
            'isActive' => $this->is_active,
            'mustChangePassword' => (bool) $this->must_change_password,
            'shopIds' => $this->whenLoaded('shops', fn (): array => $this->shops->modelKeys()),
            'shops' => $this->whenLoaded('shops', fn (): array => $this->shops->pluck('name')->all()),
        ];
    }
}
