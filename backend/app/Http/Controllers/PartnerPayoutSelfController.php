<?php

namespace App\Http\Controllers;

use App\Http\Resources\PartnerDueSelfResource;
use App\Http\Resources\PartnerPayoutSelfResource;
use App\Models\PartnerDue;
use App\Models\PartnerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Mes reversements » — ce que Kaikun doit ou a déjà versé à CE partenaire
 * (propriétaire ou prestataire), en lecture seule.
 *
 * ⚠️ **Le registre existait depuis F8.16.a, mais seulement côté back-office**
 * (`AdminPartnerPayoutController`, gardé `gerer:paiements`) : un partenaire ne
 * pouvait rien voir de ce qu'on lui devait. Ce contrôleur ouvre la même donnée
 * au bénéficiaire lui-même, scopée par `beneficiary_id`, sans aucune action
 * (préparer un lot, constater un virement restent des gestes d'agent).
 */
class PartnerPayoutSelfController extends Controller
{
    /**
     * Mon dû, ligne à ligne. GET /api/v1/reversements/mine
     *
     * Filtrable par `status` ; par défaut, ce qui reste vivant (en attente ou
     * exigible) — un partenaire qui ouvre l'écran veut savoir ce qui vient,
     * pas fouiller son historique soldé.
     */
    public function dues(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $dues = PartnerDue::query()
            ->where('beneficiary_id', $request->user()->id)
            ->with('source')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
                fn ($q) => $q->vivantes(),
            )
            ->latest('eligible_at')
            ->paginate($perPage);

        return PartnerDueSelfResource::collection($dues);
    }

    /**
     * L'historique de mes versements. GET /api/v1/reversements/mine/payouts
     */
    public function payouts(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $payouts = PartnerPayout::query()
            ->where('beneficiary_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return PartnerPayoutSelfResource::collection($payouts);
    }

    /**
     * Téléchargement de mon justificatif, par URL signée.
     * GET /api/v1/payouts/{payout}/proof/mine
     *
     * ⚠️ **Signature seule, comme les pendants admin et gestion locative**
     * (`AdminPartnerPayoutController::proof()`,
     * `MandateManagementController::downloadPayoutProof()`) : le frontend pose
     * `proof_url` en `[href]` brut, une simple navigation de navigateur qui ne
     * porte pas le jeton Sanctum — vérifier `auth()->user()` ici serait donc
     * toujours refusé, y compris pour le bon partenaire. La sécurité tient à
     * la signature (10 minutes, un seul `payout` visé), pas à une double
     * garde impraticable avec ce mode de téléchargement.
     */
    public function proof(PartnerPayout $payout): StreamedResponse
    {
        abort_if($payout->proof_path === null, 404);

        return Storage::disk($payout->proof_disk ?? 'local')
            ->download($payout->proof_path, $payout->proof_original_name ?? 'justificatif');
    }
}
