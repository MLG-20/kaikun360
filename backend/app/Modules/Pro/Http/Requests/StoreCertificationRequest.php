<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'ajout d'un document de certification
 * (POST /api/v1/providers/certifications).
 *
 * Une certification ajoutée est TOUJOURS « non vérifiée » : la vérification est
 * une action back-office (le contrôleur n'accepte donc pas `verified`).
 */
class StoreCertificationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],

            // Justificatif FACULTATIF (F8.0) — et c'est un choix, pas un oubli :
            // un prestataire doit pouvoir déclarer sa certification tout de
            // suite et revenir déposer le scan quand il l'a numérisé. Le
            // back-office voit la différence (colonne fichier vide) et n'a
            // aucune raison de vérifier une pièce qu'il n'a pas reçue.
            // Mêmes bornes que le KYC : PDF/JPG/PNG, 5 Mo.
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la certification est obligatoire.',
            'file.mimes' => 'Le justificatif doit être au format PDF, JPG ou PNG.',
            'file.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ];
    }
}
