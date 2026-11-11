<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
        return [
            'institute_name' => ['required', 'string', 'max:120'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institute_name.required' => 'Le nom de l’institut est obligatoire.',
            'late_tolerance_minutes.required' => 'Indiquez la tolérance de retard.',
            'late_tolerance_minutes.integer' => 'La tolérance doit être un nombre de minutes.',
            'late_tolerance_minutes.min' => 'La tolérance ne peut pas être négative.',
            'late_tolerance_minutes.max' => 'La tolérance ne peut pas dépasser 60 minutes.',
        ];
    }
}
