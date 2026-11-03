<?php

namespace App\Http\Requests;

use App\Enums\PunchType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPunchRequest extends FormRequest
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
            'uuid' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PunchType::class)],
            'scanned_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uuid.required' => 'Identifiant de pointage manquant.',
            'uuid.uuid' => 'Identifiant de pointage invalide.',
            'token.required' => 'Code de la borne manquant.',
            'type.required' => 'Action manquante.',
            'type.enum' => 'Cette action n\'existe pas.',
            'scanned_at.required' => 'Heure du scan manquante.',
            'scanned_at.date' => 'Heure du scan invalide.',
        ];
    }
}
