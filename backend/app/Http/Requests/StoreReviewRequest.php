<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'un avis (POST /api/v1/reviews).
 *
 * L'éligibilité « a consommé le service » est vérifiée dans le contrôleur via
 * la policy `create` de Review (preuve = réservation terminée).
 */
class StoreReviewRequest extends FormRequest
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
            'reviewable_type' => ['required', 'string', Rule::in(array_keys(Review::TYPES))],
            'reviewable_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
