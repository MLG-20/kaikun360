<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Http\Requests\StoreCertificationRequest;
use App\Modules\Pro\Http\Requests\UpdateProviderProfileRequest;
use App\Modules\Pro\Http\Resources\ProviderCertificationResource;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Mes services » — édition du profil prestataire et de ses documents de
 * certification par le prestataire lui-même (module Pro, espace connecté F5).
 *
 * Tout est scopé au **profil prestataire du compte connecté** (404 s'il n'en a
 * pas). L'inscription initiale reste `ProviderRegistrationController@store` ; ce
 * contrôleur porte la mise à jour ultérieure.
 *
 * ⚠️ L'édition du profil **ne modifie pas** le statut de validation : corriger
 * une description ne doit pas re-déclencher une revue back-office. De même, une
 * certification ajoutée ici est toujours « non vérifiée » (la vérification est
 * une action agent, cf. `ProviderValidationController`).
 */
class ProviderProfileController extends Controller
{
    /**
     * Le profil prestataire du compte connecté (404 sinon).
     */
    private function providerFor(Request $request): Provider
    {
        return Provider::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * Met à jour mon profil prestataire. PUT /api/v1/providers/mine
     *
     * Champs éditables : raison sociale, catégorie, présentation. Le statut, la
     * notation et les certifications ne passent pas par ici.
     */
    public function update(UpdateProviderProfileRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);

        $provider->update($request->validated());

        return ApiResponse::success([
            'provider' => ProviderResource::make($provider->load('certifications')),
        ]);
    }

    /**
     * Ajoute une certification, avec son justificatif éventuel.
     * POST /api/v1/providers/certifications  (multipart si un fichier est joint)
     *
     * Créée « non vérifiée » (le champ `verified` a false par défaut) : la
     * vérification relève du back-office.
     *
     * **F8.0 — le justificatif est enfin stocké.** Jusque-là, « Mes services »
     * ne faisait que *déclarer* une certification : la colonne `file_path`
     * existait mais restait vide, et l'agent qui ouvrait Comptes → Documents
     * voyait une ligne sans pièce à contrôler. Le fichier va sur le disque
     * PRIVÉ (patron KYC) et ne se relit que par URL signée.
     */
    public function storeCertification(StoreCertificationRequest $request): JsonResponse
    {
        $provider = $this->providerFor($request);
        $data = $request->validated();

        // Le fichier ne fait pas partie des colonnes : on le retire du tableau
        // avant l'insertion, sinon Eloquent tenterait d'écrire un objet
        // UploadedFile dans `file`, colonne qui n'existe pas.
        unset($data['file']);

        // `verified => false` explicite : le défaut DB ne s'applique pas à
        // l'instance renvoyée (sinon la Resource sérialiserait `verified: null`).
        $attributes = [...$data, 'verified' => false];

        if ($file = $request->file('file')) {
            // Rangé par prestataire, nom aléatoire généré par store() : deux
            // « diplome.pdf » ne s'écrasent pas et le nom d'origine (fourni par
            // l'utilisateur) ne sert jamais de chemin.
            $attributes['file_path'] = $file->store("certifications/{$provider->id}", 'local');
            $attributes['disk'] = 'local';
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['mime_type'] = $file->getClientMimeType();
            $attributes['size'] = $file->getSize();
        }

        $certification = $provider->certifications()->create($attributes);

        // Journal d'audit, comme pour toute pièce justificative déposée.
        if ($certification->hasFile()) {
            activity()->causedBy($request->user())->performedOn($certification)
                ->log('Dépôt du justificatif de certification');
        }

        return ApiResponse::created([
            'certification' => ProviderCertificationResource::make($certification),
        ]);
    }

    /**
     * Supprime une certification (et son justificatif).
     * DELETE /api/v1/providers/certifications/{certification}
     */
    public function destroyCertification(
        Request $request,
        ProviderCertification $certification,
    ): JsonResponse {
        $provider = $this->providerFor($request);

        // Cloisonnement : on ne supprime que ses propres certifications.
        abort_unless($certification->provider_id === $provider->id, 404);

        // Le fichier part AVEC la ligne : le laisser sur le disque, ce serait
        // conserver une pièce personnelle que plus rien ne référence.
        $certification->deleteFile();
        $certification->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * Téléchargement d'un justificatif.
     * GET /api/v1/providers/certifications/{certification}/download
     *
     * Route protégée par le middleware « signed » (pas par un jeton) : l'accès
     * n'est possible qu'avec l'URL signée non expirée produite par
     * `ProviderCertificationResource`. Même schéma que les pièces KYC.
     */
    public function downloadCertification(ProviderCertification $certification): StreamedResponse
    {
        abort_unless($certification->hasFile(), 404);

        $disk = $certification->disk ?? 'local';

        abort_unless(Storage::disk($disk)->exists($certification->file_path), 404);

        return Storage::disk($disk)->download(
            $certification->file_path,
            $certification->original_name,
        );
    }
}
