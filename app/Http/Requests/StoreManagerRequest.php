<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => $email === '' ? null : $email,
            'phone' => preg_replace('/\s+/', '', (string) $this->input('phone')),
            'shop_ids' => $this->input('role') === UserRole::SuperAdmin->value ? [] : $this->input('shop_ids', []),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $manager = $this->manager();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($manager)],
            'phone' => ['required', 'regex:/^[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($manager)],
            'password' => [$manager === null ? 'required' : 'nullable', 'string', 'min:8'],
            'must_change_password' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::SuperAdmin->value])],
            'shop_ids' => ['array', Rule::requiredIf($this->input('role') === UserRole::Admin->value)],
            'shop_ids.*' => ['integer', Rule::exists('shops', 'id')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->manager()?->is($this->user()) !== true) {
                    return;
                }

                if ($this->input('role') !== UserRole::SuperAdmin->value) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas retirer votre propre accès super administrateur.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Vous ne pouvez pas désactiver votre propre compte.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Le téléphone est obligatoire.',
            'phone.regex' => 'Le téléphone doit contenir entre 8 et 15 chiffres.',
            'phone.unique' => 'Ce téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'email.unique' => 'Cet e-mail est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'shop_ids.required' => 'Choisissez au moins une boutique.',
            'shop_ids.*.exists' => 'Cette boutique n\'existe pas.',
        ];
    }

    protected function manager(): ?User
    {
        $manager = $this->route('manager');

        return $manager instanceof User ? $manager : null;
    }
}
