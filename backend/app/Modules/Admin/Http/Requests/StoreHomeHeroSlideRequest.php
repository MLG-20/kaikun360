<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout d'une photo au diaporama du héros de l'accueil (F15.1).
 */
class StoreHomeHeroSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Mêmes bornes qu'un bandeau F12 : la photo s'étire en plein fond.
            'image' => [
                'required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192',
                'dimensions:min_width=1400,min_height=500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.dimensions' => 'L’image est trop petite pour un fond de page : il faut au moins 1400 px de large et 500 px de haut (idéalement 2560 × 1000 px, en paysage).',
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
            'image.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
        ];
    }
}
