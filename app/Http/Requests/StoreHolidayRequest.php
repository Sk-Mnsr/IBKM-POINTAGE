<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHolidayRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('date')) {
                    return;
                }

                if (Holiday::query()->whereDate('date', $this->string('date')->toString())->exists()) {
                    $validator->errors()->add('date', 'Un jour férié est déjà enregistré à cette date.');
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
            'date.required' => 'Choisissez la date.',
            'date.date_format' => 'La date n’est pas valide.',
            'name.required' => 'Indiquez le nom du jour férié.',
        ];
    }
}
