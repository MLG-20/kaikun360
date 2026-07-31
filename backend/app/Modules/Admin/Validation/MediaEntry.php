<?php

namespace App\Modules\Admin\Validation;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Normalise la **galerie média** d'une ressource pour le back-office (F8.1).
 *
 * Un agent ne peut pas publier une annonce sur le site vitrine sans avoir vu ce
 * qu'elle montre : jusqu'ici la file de validation ne transportait que le titre
 * et le déposant, et la décision se prenait à l'aveugle. Ce helper produit la
 * même forme pour tous les types validables.
 *
 * On expose ici les médias MASQUÉS en plus des actifs — c'est un écran de
 * modération : l'agent doit voir ce qu'il a écarté pour pouvoir le rétablir.
 * Ne jamais réutiliser cette forme sur une route publique.
 */
final class MediaEntry
{
    /**
     * Nombre de vignettes embarquées dans la file (aperçu). La page détail, elle,
     * renvoie la galerie complète.
     */
    public const PREVIEW = 4;

    /**
     * Un média normalisé.
     *
     * @return array<string, mixed>
     */
    public static function from(Media $media): array
    {
        return [
            'id' => $media->id,
            'reference' => $media->reference,
            'type' => $media->type->value,
            'url' => $media->resolveUrl(),
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'is_primary' => $media->is_primary,
            'position' => $media->position,
            'status' => $media->status->value,
            'status_label' => $media->status->label(),
            // Un média masqué reste dans la liste de modération, signalé comme tel.
            'is_hidden' => $media->status === MediaStatus::MASQUE,
        ];
    }

    /**
     * Résumé de galerie : compteurs + aperçu limité, pour une ligne de file.
     *
     * `$limit` à `null` renvoie la galerie entière (page détail).
     *
     * @return array<string, mixed>
     */
    public static function summary(Model $model, ?int $limit = self::PREVIEW): array
    {
        $media = self::mediaOf($model);

        $images = $media->filter(fn (Media $m) => $m->type === MediaType::IMAGE);
        $videos = $media->filter(fn (Media $m) => $m->type === MediaType::VIDEO);
        $hidden = $media->filter(fn (Media $m) => $m->status === MediaStatus::MASQUE);

        $items = $limit === null ? $media : $media->take($limit);

        return [
            'total' => $media->count(),
            'images' => $images->count(),
            'videos' => $videos->count(),
            'hidden' => $hidden->count(),
            'items' => $items->map(fn (Media $m) => self::from($m))->values()->all(),
        ];
    }

    /**
     * Les médias d'une ressource, masqués compris.
     *
     * Toutes les ressources validables ne sont pas illustrables : les
     * prestataires n'ont pas de galerie (absents de `Media::TYPES`). On renvoie
     * alors une collection vide plutôt que d'échouer, pour que la file reste
     * uniforme d'un onglet à l'autre.
     *
     * @return Collection<int, Media>
     */
    private static function mediaOf(Model $model): Collection
    {
        if (! method_exists($model, 'allMedia')) {
            return collect();
        }

        // `getRelationValue` réutilise la relation déjà chargée (eager loading)
        // au lieu de relancer une requête par ligne de la file.
        return $model->getRelationValue('allMedia') ?? collect();
    }
}
