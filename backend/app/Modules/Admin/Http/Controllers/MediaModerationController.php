<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Admin\Validation\MediaEntry;
use App\Modules\Admin\Validation\ValidatorRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modération d'un média par un agent du back-office (F8.1).
 *
 * Complète la file de validation : plutôt que de refuser une annonce entière
 * parce qu'une photo est floue ou hors sujet, l'agent **écarte cette photo** et
 * publie le reste. Le média masqué n'est pas supprimé — il sort des annonces
 * publiques tout en restant consultable en back-office, ce que la colonne
 * `media.status` prévoyait depuis B12 sans que rien ne l'expose.
 *
 * Autorisation : la permission fine du TYPE PARENT (`valider:bien` pour la photo
 * d'un bien…). Qui a le droit de publier une ressource a le droit d'arbitrer ce
 * qu'elle montre ; l'inverse ouvrirait une modération sans mandat.
 */
class MediaModerationController extends Controller
{
    /**
     * Masque ou réaffiche un média. PATCH /api/v1/admin/media/{media}/status
     *
     * Corps : { "status": "actif"|"masque" }
     */
    public function update(Request $request, Media $media, ValidatorRegistry $registry): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', MediaStatus::values())],
        ]);

        $this->authorizeFor($request, $media, $registry);

        $status = MediaStatus::from($data['status']);
        $media->update(['status' => $status->value]);

        activity()->causedBy($request->user())->performedOn($media)
            ->withProperties(['status' => $status->value])
            ->log($status === MediaStatus::MASQUE ? 'Masquage de média' : 'Réaffichage de média');

        return ApiResponse::success(['media' => MediaEntry::from($media->refresh())]);
    }

    /**
     * Exige la permission de validation du type auquel le média est rattaché.
     *
     * Un média orphelin (ressource supprimée) ou rattaché à un type non
     * validable n'a pas de mandat de modération identifiable → 403 plutôt
     * qu'une autorisation par défaut.
     */
    private function authorizeFor(Request $request, Media $media, ValidatorRegistry $registry): void
    {
        // `Media::TYPES` fait correspondre clé publique → classe ; on l'inverse
        // pour retrouver le type validable à partir de la cible du média.
        $typeKey = array_search($media->mediable_type, Media::TYPES, strict: true);

        abort_if($typeKey === false, 403, "Ce média n'est rattaché à aucun type modérable.");

        // `for()` lèverait une 404 sur un type absent du registre : on veut un
        // 403, la ressource existe mais aucun mandat ne couvre sa modération.
        abort_unless(in_array($typeKey, $registry->types(), strict: true), 403);

        $permission = $registry->for($typeKey)->permission();

        abort_unless($request->user()->can($permission), 403);
    }
}
