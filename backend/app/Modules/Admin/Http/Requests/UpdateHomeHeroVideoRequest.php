<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt de la vidéo de fond du héros de l'accueil (F15.1).
 *
 * Comme pour les actualités : un fichier (déjà compressé côté équipe, aucun
 * transcodage ici) OU un lien d'embed, jamais les deux traités en même temps
 * — le fichier l'emporte s'il est présent.
 */
class UpdateHomeHeroVideoRequest extends FormRequest
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
            'video' => ['sometimes', 'file', 'mimes:mp4,webm,mov,quicktime', 'max:81920'],
            'video_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'remove_video' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.max' => 'La vidéo ne doit pas dépasser 80 Mo — compressez-la avant de la déposer.',
            'video.mimes' => 'Formats vidéo acceptés : MP4, WebM ou MOV.',
        ];
    }
}
