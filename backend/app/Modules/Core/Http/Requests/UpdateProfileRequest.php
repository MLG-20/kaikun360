<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la mise à jour du profil (PATCH /api/v1/users/me).
 *
 * Mise à jour PARTIELLE : `sometimes` ne valide que les champs réellement
 * envoyés. Depuis F3.2b, l'e-mail et le téléphone SONT modifiables — mais leur
 * changement déclenche une **re-vérification** du nouveau contact (le contrôleur
 * remet le canal à « non vérifié » et renvoie un code). La localisation reprend
 * le référentiel géo des biens (cascade Région → Département → Commune) et
 * vérifie la cohérence (département dans la région, commune dans le département).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'utilisateur met à jour son propre profil (endpoint /me).
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            // E-mail obligatoire s'il est fourni, unique hors soi-même.
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Téléphone facultatif (peut être vidé), unique hors soi-même.
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],

            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Localisation structurée (facultative), cohérente en cascade.
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'department_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('departments', 'id')->where('region_id', $this->input('region_id')),
            ],
            'commune_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('communes', 'id')->where('department_id', $this->input('department_id')),
            ],

            'preferences' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom ne peut pas être vide.',
            'email.required' => "L'adresse e-mail ne peut pas être vide.",
            'email.email' => "L'adresse e-mail est invalide.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'region_id.exists' => 'La région sélectionnée est invalide.',
            'department_id.exists' => "Le département est invalide ou n'appartient pas à la région.",
            'commune_id.exists' => "La commune est invalide ou n'appartient pas au département.",
            'preferences.array' => 'Les préférences doivent être un objet clé/valeur.',
        ];
    }
}
