<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON PUBLIQUE d'un article d'actualité (F15).
 *
 * @mixin \App\Models\NewsArticle
 */
class NewsArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'image' => $this->imageUrl(),
            // Le fichier déposé l'emporte sur l'URL d'embed quand les deux
            // existent — voir NewsArticle.
            'video_file' => $this->videoFileUrl(),
            'video_url' => $this->video_path ? null : $this->video_url,
            'published_at' => $this->updated_at,
        ];
    }
}
