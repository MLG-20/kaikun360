<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un article d'actualité (F15). Requête multipart : l'image (et,
 * le cas échéant, la vidéo) voyage avec le texte en un seul envoi.
 */
class StoreNewsArticleRequest extends FormRequest
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
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string'],
            // Obligatoire à la création : un article sans image ne peut pas
            // s'afficher dans la grille (voir NewsArticle).
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            // ⚠️ Vidéo déjà compressée par l'équipe avant dépôt — aucun
            // transcodage n'est fait ici, seulement stockée. 80 Mo laisse de
            // la marge pour un clip court déjà compressé.
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov,quicktime', 'max:81920'],
            'video_url' => ['nullable', 'url', 'max:500'],
            // F17 — une « carte » sans article rédigé : le bouton public
            // pointe ici plutôt que vers la page de l'article (voir
            // NewsArticle, HomePageComponent côté frontend).
            'link_url' => ['nullable', 'url', 'max:500'],
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
            'image.required' => 'Une image de couverture est obligatoire.',
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
            'video.max' => 'La vidéo ne doit pas dépasser 80 Mo — compressez-la avant de la déposer.',
            'video.mimes' => 'Formats vidéo acceptés : MP4, WebM ou MOV.',
        ];
    }
}
