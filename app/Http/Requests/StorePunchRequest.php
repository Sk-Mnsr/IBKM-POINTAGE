<?php

namespace App\Http\Requests;

use App\Enums\PunchType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === UserRole::Staff && $user->is_active;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PunchType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Scannez le code affiché sur la tablette.',
            'type.required' => 'Choisissez une action.',
            'type.enum' => 'Cette action n\'existe pas.',
        ];
    }
}
