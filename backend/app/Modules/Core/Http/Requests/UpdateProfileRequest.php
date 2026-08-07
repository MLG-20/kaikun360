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
        $user = $this->user();
        $userId = $user->id;

        return [
            /**
             * ⚠️ **F8.22 — le mot de passe actuel est exigé pour changer
             * l'ADRESSE DE CONNEXION.**
             *
             * Le trou était réel et il visait le compte le plus sensible : rien
             * n'empêchait, avec une session ouverte (poste laissé déverrouillé,
             * jeton dérobé), de remplacer l'e-mail d'un **super administrateur**
             * par le sien, puis d'utiliser « mot de passe oublié » sur cette
             * nouvelle adresse. Le compte changeait de mains sans jamais
             * connaître le mot de passe. Le changement de mot de passe, lui,
             * était déjà protégé de cette façon depuis F3.2b — l'e-mail ouvre la
             * même porte et ne l'était pas.
             *
             * La règle ne se déclenche **que si l'adresse change vraiment**
             * (`emailChange()`) : renvoyer son propre e-mail avec le reste du
             * formulaire ne doit rien réclamer.
             *
             * ⚠️ **Exception assumée pour les comptes Google** : leur mot de
             * passe est une chaîne aléatoire qu'ils n'ont jamais vue (`Str::password`
             * à la création), le leur demander les empêcherait purement et
             * simplement de corriger leur adresse.
             */
            'current_password' => [
                $this->exigeMotDePasse() ? 'required' : 'nullable',
                'string',
                Rule::when($this->exigeMotDePasse(), ['current_password:sanctum']),
            ],
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

    /** L'adresse de connexion est-elle réellement modifiée par cette requête ? */
    public function emailChange(): bool
    {
        return $this->filled('email') && $this->string('email')->value() !== $this->user()->email;
    }

    /** Faut-il réclamer le mot de passe actuel pour cette requête ? */
    private function exigeMotDePasse(): bool
    {
        return $this->emailChange() && $this->user()->google_id === null;
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
            'current_password.required' => "Votre mot de passe actuel est obligatoire pour changer d'adresse e-mail.",
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'region_id.exists' => 'La région sélectionnée est invalide.',
            'department_id.exists' => "Le département est invalide ou n'appartient pas à la région.",
            'commune_id.exists' => "La commune est invalide ou n'appartient pas au département.",
            'preferences.array' => 'Les préférences doivent être un objet clé/valeur.',
        ];
    }
}
