<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'une pièce justificative (POST /api/v1/users/me/documents).
 *
 * Contraintes : type autorisé, fichier obligatoire, formats restreints
 * (PDF/JPG/PNG) et taille maximale (5 Mo) — pour éviter les abus de stockage.
 */
class StoreDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::in(DocumentType::values())],
            // max:5120 = 5 Mo (la valeur est en kilo-octets).
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de document est obligatoire.',
            'type.in' => 'Le type de document est invalide.',
            'file.required' => 'Le fichier est obligatoire.',
            'file.mimes' => 'Le fichier doit être au format PDF, JPG ou PNG.',
            'file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
