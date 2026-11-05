<?php

namespace App\Http\Requests;

use App\Support\ShopContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('shop_id')) {
            $this->merge(['shop_id' => app(ShopContext::class)->defaultShopId()]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', Rule::in($this->user()?->accessibleShopIds() ?? [])],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where('shop_id', (int) $this->input('shop_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du département est obligatoire.',
            'name.unique' => 'Ce département existe déjà dans cette boutique.',
            'shop_id.required' => 'Choisissez la boutique.',
            'shop_id.in' => 'Vous ne gérez pas cette boutique.',
        ];
    }
}
