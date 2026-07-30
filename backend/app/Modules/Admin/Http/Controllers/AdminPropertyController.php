<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\AdminUpdatePropertyRequest;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Correction et archivage d'un bien depuis le back-office — F7.3.g.
 *
 * Solde la **dette CDC §15** (« un admin peut modifier ») et la ligne §6 *Biens
 * immobiliers* : valider et publier existaient (B2.4), **modifier** et
 * **archiver** n'avaient aucune route côté back-office.
 *
 * **Périmètre arbitré avec le porteur du produit** : corriger et archiver, en
 * traçant tout. On ne **crée pas** un bien à la place d'un propriétaire et on ne
 * le **réattribue pas** à un autre compte — réattribuer change qui touche les
 * loyers, c'est un geste qui ne se rattrape pas d'un clic. Le bien reste à son
 * propriétaire.
 *
 * Garde : permission `valider:bien` — celui à qui l'on confie déjà la publication
 * ou le rejet d'une annonce peut en corriger le titre.
 */
class AdminPropertyController extends Controller
{
    /**
     * Corrige les champs d'un bien. PATCH /api/v1/admin/properties/{property}
     *
     * Réutilise les règles du propriétaire (champs, cohérence géographique). Le
     * **statut n'est pas modifiable ici** : il relève de la file de validation et
     * de l'archivage ci-dessous, qui tracent chacun leur décision.
     */
    public function update(AdminUpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $before = $property->only(['title', 'price_xof']);

        $property->update($request->validated());

        // Une correction faite par l'équipe sur le bien d'un tiers se trace plus
        // finement qu'une modification par le propriétaire : on garde l'avant/après
        // des deux champs qui engagent (l'intitulé public et le prix).
        activity()
            ->causedBy($request->user())
            ->performedOn($property)
            ->withProperties([
                'from' => $before,
                'to' => $property->only(['title', 'price_xof']),
            ])
            ->log('Correction de bien (back-office)');

        return ApiResponse::success([
            'property' => PropertyResource::make($this->loaded($property)),
        ]);
    }

    /**
     * Archive un bien. PATCH /api/v1/admin/properties/{property}/archive
     *
     * Sort l'annonce du catalogue sans la supprimer : l'historique des
     * réservations et des documents reste intact.
     */
    public function archive(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($property->status === PropertyStatus::ARCHIVE) {
            throw ValidationException::withMessages([
                'status' => ['Ce bien est déjà archivé.'],
            ]);
        }

        $property->update(['status' => PropertyStatus::ARCHIVE->value]);

        activity()
            ->causedBy($request->user())
            ->performedOn($property)
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->log('Archivage de bien');

        return ApiResponse::success([
            'property' => PropertyResource::make($this->loaded($property)),
        ]);
    }

    /**
     * Sort un bien de l'archive. PATCH /api/v1/admin/properties/{property}/restore
     *
     * Le bien repart **en attente de validation**, jamais directement publié : il a
     * pu être archivé pour un contenu problématique, le republier sans relecture
     * remettrait ce contenu en ligne d'un clic.
     */
    public function restore(Request $request, Property $property): JsonResponse
    {
        if ($property->status !== PropertyStatus::ARCHIVE) {
            throw ValidationException::withMessages([
                'status' => ["Ce bien n'est pas archivé."],
            ]);
        }

        $property->update(['status' => PropertyStatus::EN_ATTENTE_VALIDATION->value]);

        activity()->causedBy($request->user())->performedOn($property)
            ->log('Sortie d’archive (retour en file de validation)');

        return ApiResponse::success([
            'property' => PropertyResource::make($this->loaded($property)),
        ]);
    }

    /**
     * Recharge le bien avec ses relations d'affichage (anti-N+1 côté Resource).
     */
    private function loaded(Property $property): Property
    {
        return $property->fresh()->load(['region', 'department', 'commune', 'owner', 'stay', 'media']);
    }
}
