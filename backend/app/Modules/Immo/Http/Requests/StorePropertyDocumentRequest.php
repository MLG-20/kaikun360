<?php

namespace App\Modules\Immo\Http\Requests;

use App\Modules\Immo\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ajout d'un document à un bien
 * (POST /api/v1/properties/{property}/documents).
 */
class StorePropertyDocumentRequest extends FormRequest
{
    /**
     * Réservé au propriétaire du bien (ou admin) — via la policy.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('manageDocuments', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['titre_foncier', 'bail', 'plan', 'autre'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 Mo
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
