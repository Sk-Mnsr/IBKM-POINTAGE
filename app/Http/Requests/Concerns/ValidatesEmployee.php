<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesEmployee
{
    protected function prepareEmployeeInput(): void
    {
        $email = $this->input('email');
        $departmentId = $this->input('department_id');

        $this->merge([
            'email' => $email === '' ? null : $email,
            'department_id' => $departmentId === '' ? null : $departmentId,
            'phone' => preg_replace('/\s+/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function employeeRules(?User $employee = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'phone' => ['required', 'regex:/^[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($employee)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'password' => [$employee === null ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*' => ['array'],
            'schedules.*.starts_at' => ['nullable', 'date_format:H:i'],
            'schedules.*.ends_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('schedules', []) as $weekday => $schedule) {
                    if (! is_array($schedule)) {
                        continue;
                    }

                    $start = $schedule['starts_at'] ?? '';
                    $end = $schedule['ends_at'] ?? '';

                    if ($start === '' && $end === '') {
                        continue;
                    }

                    if ($start === '' || $end === '') {
                        $validator->errors()->add("schedules.{$weekday}.starts_at", 'Indiquez l\'heure de début et de fin.');

                        continue;
                    }

                    if ($start >= $end) {
                        $validator->errors()->add("schedules.{$weekday}.ends_at", 'L\'heure de fin doit être après l\'heure de début.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function employeeMessages(): array
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
            'department_id.exists' => 'Ce département n\'existe pas.',
        ];
    }

    public function authorizeAdmin(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }
}
