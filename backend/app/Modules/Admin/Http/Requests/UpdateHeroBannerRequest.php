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
 * arrive souvent brute d'appareil — et on a tout intérêt à la recevoir grande.
 * Elle est recompressée et ramenée à `ImageProcessor::BACKGROUND_MAX_WIDTH`
 * (2560 px) avant stockage, soit bien plus que les 1600 px d'une photo
 * d'annonce : étirée sur toute la largeur de l'écran, une image plus étroite
 * serait agrandie par le navigateur, donc floue.
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
            // ⚠️ La règle de DIMENSIONS n'est pas un excès de zèle : c'est le
            // seul endroit où le problème peut encore être signalé. Une fois la
            // photo stockée, plus rien ne la sauve — `scaleDown` réduit, il
            // n'agrandit pas — et le défaut ne se voit qu'à l'écran, en
            // production, sur un bandeau devenu flou. Constaté en recette : une
            // vignette de 360 × 360 px déposée comme fond de page, agrandie
            // sept fois par le navigateur.
            'image' => [
                'sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192',
                'dimensions:min_width=1400,min_height=500',
            ],
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
            'image.dimensions' => 'L’image est trop petite pour un fond de page : il faut au moins 1400 px de large et 500 px de haut (idéalement 2560 × 1000 px, en paysage). Une image plus petite serait agrandie par le navigateur et paraîtrait floue.',
            'image.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
            'title.max' => 'Le titre ne doit pas dépasser 180 caractères.',
            'lead.max' => 'L’accroche ne doit pas dépasser 600 caractères.',
        ];
    }
}
