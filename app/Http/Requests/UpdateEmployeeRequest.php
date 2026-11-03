<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployee;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');

        return $this->employeeRules($employee instanceof User ? $employee : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }
}
