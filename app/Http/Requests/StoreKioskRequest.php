<?php

namespace App\Http\Requests;

use App\Models\Kiosk;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Shop $shop */
        $shop = $this->route('shop');
        $kiosk = $this->route('kiosk');

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('kiosks', 'name')
                    ->where('shop_id', $shop->id)
                    ->ignore($kiosk instanceof Kiosk ? $kiosk->id : null),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Donnez un nom à la borne, par exemple « Entrée arrière ».',
            'name.unique' => 'Cette boutique a déjà une borne avec ce nom.',
        ];
    }
}
