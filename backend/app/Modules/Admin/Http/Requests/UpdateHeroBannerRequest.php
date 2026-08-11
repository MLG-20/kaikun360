<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un bandeau d'en-tête (F12).
 *
 * Requête **multipart** : l'image voyage avec les textes en un seul envoi, pour
 * que l'équipe n'ait pas à enregistrer deux fois. Tous les champs sont
 * facultatifs — on n'écrase que ce qui est effectivement transmis.
 *
 * ⚠️ Le champ `image` est une IMAGE DE FOND plein écran : la limite de poids est
 * plus généreuse que celle des photos d'annonce (5 Mo), car une photo de héros
 * arrive souvent brute d'appareil. Elle est de toute façon recompressée et
 * ramenée à 1600 px de large avant stockage.
 */
class UpdateHeroBannerRequest extends FormRequest
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
            'image' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            // `nullable` et non `filled` : envoyer une chaîne vide est le geste
            // qui RETIRE une surcharge de texte et rend à la page son libellé
            // d'origine. C'est un usage normal, pas une erreur de saisie.
            'eyebrow' => ['sometimes', 'nullable', 'string', 'max:120'],
            'title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'lead' => ['sometimes', 'nullable', 'string', 'max:600'],
            // Retire l'image propre du bandeau : la page retombe alors sur
            // l'image de sa page parente, ou sur son dégradé d'origine.
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
            'image.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
            'title.max' => 'Le titre ne doit pas dépasser 180 caractères.',
            'lead.max' => 'L’accroche ne doit pas dépasser 600 caractères.',
        ];
    }
}
