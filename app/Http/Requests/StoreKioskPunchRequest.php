<?php

namespace App\Http\Requests;

use App\Enums\PunchType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKioskPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(PunchType::class)],
        ];
    }
}
