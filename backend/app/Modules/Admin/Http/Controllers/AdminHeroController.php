<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use App\Modules\Admin\Http\Requests\UpdateHeroBannerRequest;
use App\Services\ImageProcessor;
use App\Support\ApiResponse;
use App\Support\Heroes\HeroCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Pilotage des bandeaux d'en-tête depuis le back-office (F12) — CDC §6
 * « Paramètres » (contenus des pages), permission `gerer:parametres`.
 *
 * Le geste attendu de l'équipe est simple : choisir une page, déposer une
 * photo, éventuellement retoucher les mots, enregistrer. Le reste (compression,
 * héritage vers les pages filles, invalidation du cache public) se fait ici.
 */
class AdminHeroController extends Controller
{
    /**
     * Tous les bandeaux connus, saisis ou non. GET /api/v1/admin/heroes
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'heroes' => HeroCatalog::forAdmin(),
            // Libellés des groupes : ils vivent dans le catalogue, pas dans
            // l'écran Angular, pour qu'un nouveau groupe apparaisse tout seul.
            'groups' => (object) HeroCatalog::GROUPS,
        ]);
    }

    /**
     * Crée ou met à jour le bandeau d'une page.
     * POST /api/v1/admin/heroes/{key} (multipart)
     *
     * Seuls les champs réellement transmis sont touchés : envoyer une image
     * n'efface pas les textes, et enregistrer des textes ne perd pas l'image.
     */
    public function update(UpdateHeroBannerRequest $request, string $key, ImageProcessor $images): JsonResponse
    {
        // Clé inconnue = 404 franc. Accepter n'importe quelle chaîne
        // remplirait la table de bandeaux fantômes qu'aucune page ne lit.
        abort_unless(HeroCatalog::knows($key), 404, 'Bandeau inconnu.');

        $data = $request->validated();

        $banner = HeroBanner::firstOrNew(['key' => $key]);

        foreach (['eyebrow', 'title', 'lead'] as $field) {
            if (array_key_exists($field, $data)) {
                // Chaîne vide → `null` : « pas de surcharge », donc la page
                // retrouve le texte de son gabarit. Voir HeroCatalog.
                $value = trim((string) $data[$field]);
                $banner->{$field} = $value === '' ? null : $value;
            }
        }

        // Retrait explicite de l'image, ou remplacement par une nouvelle : dans
        // les deux cas l'ancien fichier n'a plus aucune raison d'occuper le
        // disque. On le supprime AVANT d'écrire le nouveau chemin, sinon on
        // perd la seule référence qui permettait de le retrouver.
        $removing = (bool) ($data['remove_image'] ?? false);

        if (($removing || $request->hasFile('image')) && $banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
            $banner->image_path = null;
        }

        if ($request->hasFile('image')) {
            // Bornes propres aux images de FOND : plus larges et moins
            // compressées que les photos d'annonce, sans quoi le navigateur
            // agrandit l'image sur toute la largeur de l'écran et le bandeau
            // paraît flou. Voir ImageProcessor::BACKGROUND_MAX_WIDTH.
            $stored = $images->storeCompressed(
                $request->file('image'),
                'heroes',
                ImageProcessor::BACKGROUND_MAX_WIDTH,
                ImageProcessor::BACKGROUND_JPEG_QUALITY,
            );
            $banner->image_path = $stored['path'];
        }

        $banner->updated_by = $request->user()->id;
        $banner->save();

        HeroCatalog::flush();

        activity()->causedBy($request->user())->performedOn($banner)
            ->log("Mise à jour du bandeau « {$key} »");

        return ApiResponse::success([
            'heroes' => HeroCatalog::forAdmin(),
            'groups' => (object) HeroCatalog::GROUPS,
        ]);
    }

    /**
     * Remet un bandeau à zéro. DELETE /api/v1/admin/heroes/{key}
     *
     * Efface la surcharge entière (image ET textes) : la page redevient
     * exactement ce qu'elle était avant toute personnalisation.
     */
    public function destroy(string $key): JsonResponse
    {
        abort_unless(HeroCatalog::knows($key), 404, 'Bandeau inconnu.');

        $banner = HeroBanner::where('key', $key)->first();

        if ($banner) {
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }

            activity()->causedBy(request()->user())->performedOn($banner)
                ->log("Réinitialisation du bandeau « {$key} »");

            $banner->delete();

            HeroCatalog::flush();
        }

        return ApiResponse::success([
            'heroes' => HeroCatalog::forAdmin(),
            'groups' => (object) HeroCatalog::GROUPS,
        ]);
    }
}
