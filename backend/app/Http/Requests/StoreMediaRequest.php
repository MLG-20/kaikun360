<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'un média (POST /api/v1/media).
 *
 * Deux cas : soit un fichier image (`file`) qui sera compressé et stocké, soit
 * une URL de vidéo externe (`url`). L'autorisation « propriétaire de la
 * ressource » est vérifiée dans le contrôleur (délégation à la policy `update`
 * du module), une fois la cible résolue.
 */
class StoreMediaRequest extends FormRequest
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
            // Type de ressource illustrée : clé courte de l'allow-list.
            'mediable_type' => ['required', 'string', Rule::in(array_keys(Media::TYPES))],
            'mediable_id' => ['required', 'integer', 'min:1'],

            // Image téléversée (compressée côté serveur) OU vidéo par URL.
            'file' => ['required_without:url', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'url' => ['required_without:file', 'nullable', 'url', 'max:2048'],

            'is_primary' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Fournissez une image (file) ou une URL de vidéo (url).',
            'url.required_without' => 'Fournissez une image (file) ou une URL de vidéo (url).',
            'file.max' => 'L’image ne doit pas dépasser 5 Mo.',
        ];
    }
}
