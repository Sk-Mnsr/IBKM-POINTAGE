<?php

namespace App\Http\Requests;

use App\Enums\PunchType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePunchCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'time' => ['required', 'date_format:H:i'],
            'type' => ['required', Rule::enum(PunchType::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'time.required' => 'L\'heure est obligatoire.',
            'time.date_format' => 'L\'heure n\'est pas valide.',
            'type.required' => 'Choisissez le type de pointage.',
            'type.enum' => 'Ce type de pointage n\'existe pas.',
            'reason.required' => 'Indiquez le motif de la correction.',
            'reason.min' => 'Le motif est trop court.',
            'reason.max' => 'Le motif ne doit pas dépasser 500 caractères.',
        ];
    }
}
