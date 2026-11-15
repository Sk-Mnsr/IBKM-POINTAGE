<?php

namespace App\Http\Requests;

use App\Enums\CustomerReviewRating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $comment = $this->input('comment');

        $this->merge([
            'comment' => is_string($comment) && trim($comment) === '' ? null : $comment,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', Rule::enum(CustomerReviewRating::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Choisissez une évaluation.',
            'rating.enum' => 'Cette évaluation n’est pas valide.',
            'comment.max' => 'Le commentaire ne doit pas dépasser 500 caractères.',
        ];
    }
}
