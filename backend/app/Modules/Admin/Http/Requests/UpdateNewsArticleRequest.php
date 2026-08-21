<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un article d'actualité (F15). Tous les champs sont facultatifs
 * — seuls ceux réellement transmis sont modifiés (voir AdminNewsController).
 */
class UpdateNewsArticleRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:180'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:300'],
            'body' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'video' => ['sometimes', 'file', 'mimes:mp4,webm,mov,quicktime', 'max:81920'],
            // Retire la vidéo déposée : l'article retombe sur `video_url`
            // s'il en porte une, sinon reste sans vidéo.
            'remove_video' => ['sometimes', 'boolean'],
            'video_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            // F17 — voir StoreNewsArticleRequest.
            'link_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.max' => 'L’image ne doit pas dépasser 8 Mo.',
            'video.max' => 'La vidéo ne doit pas dépasser 80 Mo — compressez-la avant de la déposer.',
            'video.mimes' => 'Formats vidéo acceptés : MP4, WebM ou MOV.',
        ];
    }
}
