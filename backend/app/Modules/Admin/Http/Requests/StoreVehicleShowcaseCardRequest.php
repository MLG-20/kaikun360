<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une carte de la vitrine « Location de véhicules » (accueil).
 * Requête multipart : l'image voyage avec le texte en un seul envoi.
 */
class StoreVehicleShowcaseCardRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:300'],
            // Obligatoire ici : c'est la clé de rangement de la vitrine
            // (berline, 4x4, minibus, ou toute autre catégorie du client).
            'category' => ['required', 'string', 'max:60'],
            // Obligatoire à la création : une carte sans image ne peut pas
            // s'afficher dans la grille (voir VehicleShowcaseCard).
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'link_url' => ['required', 'url', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:100'],
            'is_published' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'La catégorie est obligatoire (berline, 4x4, minibus…).',
            'image.required' => 'Une image de couverture est obligatoire.',
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
            'link_url.required' => 'Le lien de la carte est obligatoire.',
        ];
    }
}
