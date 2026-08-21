<?php

namespace App\Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON back-office d'un article d'actualité (F15).
 *
 * Contrairement à la ressource publique, l'équipe voit les DEUX champs vidéo
 * séparément (fichier ET url) — c'est elle qui doit comprendre lequel des deux
 * sera effectivement montré, pas seulement le visiteur.
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
            'video_file' => $this->videoFileUrl(),
            'video_url' => $this->video_url,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'is_published' => $this->is_published,
            'position' => $this->position,
            'updated_at' => $this->updated_at,
        ];
    }
}
