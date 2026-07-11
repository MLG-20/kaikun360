<?php

namespace App\Http\Requests;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la modération d'un avis (PATCH /api/v1/reviews/{review}/moderate).
 *
 * Le modérateur ne peut que publier ou rejeter (pas remettre en attente).
 * L'autorisation agents/admin est portée par la policy `moderate`.
 */
class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                ReviewStatus::PUBLIE->value,
                ReviewStatus::REJETE->value,
            ])],
        ];
    }
}
