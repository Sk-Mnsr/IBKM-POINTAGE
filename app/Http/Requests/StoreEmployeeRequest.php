<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use ValidatesEmployee;

    public function authorize(): bool
    {
        return $this->authorizeAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeeInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->employeeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }
}
