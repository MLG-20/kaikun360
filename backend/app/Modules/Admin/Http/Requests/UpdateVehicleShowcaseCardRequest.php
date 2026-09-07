<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'une carte de la vitrine « Location de véhicules » (accueil).
 * Tous les champs sont facultatifs — seuls ceux réellement transmis sont
 * modifiés (voir AdminVehicleShowcaseController).
 */
class UpdateVehicleShowcaseCardRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'category' => ['sometimes', 'string', 'max:60'],
            'image' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'link_url' => ['sometimes', 'url', 'max:500'],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
        ];
    }
}
