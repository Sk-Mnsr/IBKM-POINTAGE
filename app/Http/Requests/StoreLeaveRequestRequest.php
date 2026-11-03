<?php

namespace App\Http\Requests;

use App\Enums\LeaveType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Staff;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LeaveType::class)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Choisissez le type d\'absence.',
            'type.enum' => 'Ce type d\'absence n\'existe pas.',
            'starts_on.required' => 'Indiquez la date de début.',
            'starts_on.date_format' => 'La date de début n\'est pas valide.',
            'starts_on.after_or_equal' => 'La date de début ne peut pas être passée.',
            'ends_on.required' => 'Indiquez la date de fin.',
            'ends_on.date_format' => 'La date de fin n\'est pas valide.',
            'ends_on.after_or_equal' => 'La date de fin doit être après la date de début.',
            'reason.max' => 'Le motif ne doit pas dépasser 1000 caractères.',
        ];
    }
}
