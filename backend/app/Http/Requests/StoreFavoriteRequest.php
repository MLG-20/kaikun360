<?php

namespace App\Http\Requests;

use App\Support\Favoritables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ajout d'un favori polymorphe (POST /api/v1/favorites).
 *
 * `type` doit être un univers favorisable connu (property/stay/vehicle/
 * experience/mobility) ; l'existence ET la visibilité de l'élément sont
 * vérifiées dans le contrôleur (via `Favoritables::findVisible`).
 */
class StoreFavoriteRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(Favoritables::slugs())],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }
}
