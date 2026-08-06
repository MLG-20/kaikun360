<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\PartnerDueStatus;
use App\Enums\PartnerPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\PartnerDue;
use App\Models\PartnerPayout;
use App\Modules\Admin\Http\Resources\PartnerDueResource;
use App\Modules\Admin\Http\Resources\PartnerPayoutResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reversements aux partenaires — pilotage back-office (F8.16.a).
 *
 * ⚠️ **Ce que cet écran répare.** Kaikun encaisse et commissionne sur tous les
 * univers depuis F8.4, mais ne reversait qu'en gestion locative
 * (`owner_payouts.mandate_id` est non nullable). Jusqu'ici, **si un hôte
 * demandait ce que Kaikun lui devait, personne ne pouvait répondre.**
 *
 * ⚠️ **Garde `gerer:paiements`, sans nouvelle permission.** Reverser, c'est
 * sortir de l'argent : exactement la même nature d'acte que rembourser, et
 * `gerer:paiements` est déjà la permission de **gouvernance** du produit (un
 * agent purement opérationnel y reçoit 403, piège rencontré en F8.2). Inventer
 * `gerer:reversements` aurait dispersé la décision financière sur deux droits
 * qu'on aurait de toute façon accordés ensemble.
 *
 * ⚠️ **Aucun virement n'est exécuté par le serveur.** L'agent paie par Wave,
 * Orange Money ou virement, puis vient le CONSTATER ici avec son justificatif.
 * L'automatisation par l'API de transfert PayTech se branchera derrière, comme
 * un `PaytechProvider` — et seulement après avoir confirmé auprès d'eux que le
 * produit est activable, à quels frais, et avec quel KYC des bénéficiaires.
 */
class AdminPartnerPayoutController extends Controller
{
    /**
     * Le registre : tout ce que Kaikun doit, ligne à ligne.
     * GET /api/v1/admin/partner-dues
     *
     * Filtres : `status`, `beneficiary_id`, `per_page`.
     */
    public function dues(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $dues = PartnerDue::query()
            ->with(['beneficiary', 'source'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
                // Vue par défaut : ce qui reste dû. Ouvrir l'écran sur l'archive
                // des dettes soldées ferait chercher le travail à faire.
                fn ($q) => $q->vivantes(),
            )
            ->when(
                $request->filled('beneficiary_id'),
                fn ($q) => $q->where('beneficiary_id', $request->integer('beneficiary_id')),
            )
            ->latest('eligible_at')
            ->paginate($perPage);

        return PartnerDueResource::collection($dues);
    }

    /**
     * « À qui doit-on quoi » — l'écran d'entrée.
     * GET /api/v1/admin/partner-dues/beneficiaries
     *
     * ⚠️ **Une ligne par PARTENAIRE, pas par dette.** On ne vire pas à une
     * réservation, on vire à quelqu'un : la question de l'agent est « qui dois-je
     * payer cette semaine, et combien ». Agrégé en UNE requête (`GROUP BY`) —
     * lister les dettes puis les regrouper côté écran ferait descendre des
     * milliers de lignes pour en afficher vingt.
     */
    public function beneficiaries(Request $request): JsonResponse
    {
        $lignes = PartnerDue::query()
            ->selectRaw('beneficiary_id')
            // Deux totaux distincts, et c'est le cœur de l'écran : ce qu'on PEUT
            // payer aujourd'hui, et ce qui est acquis mais encore sous délai.
            ->selectRaw('SUM(CASE WHEN status = ? THEN net_xof ELSE 0 END) as payable_xof', [PartnerDueStatus::EXIGIBLE->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN net_xof ELSE 0 END) as pending_xof', [PartnerDueStatus::EN_ATTENTE->value])
            ->selectRaw('COUNT(*) as dues_count')
            ->selectRaw('MIN(eligible_at) as oldest_eligible_at')
            ->vivantes()
            ->whereNull('payout_id')
            ->groupBy('beneficiary_id')
            ->with('beneficiary:id,name,email,phone')
            ->get()
            // Le tri se fait après agrégation : d'abord les plus gros dus, c'est
            // là que le retard coûte le plus cher en confiance.
            ->sortByDesc('payable_xof')
            ->values()
            ->map(fn (PartnerDue $ligne) => [
                'beneficiary' => [
                    'id' => $ligne->beneficiary_id,
                    'name' => $ligne->beneficiary?->name,
                    'email' => $ligne->beneficiary?->email,
                    'phone' => $ligne->beneficiary?->phone,
                ],
                'payable_xof' => (int) $ligne->payable_xof,
                'pending_xof' => (int) $ligne->pending_xof,
                'dues_count' => (int) $ligne->dues_count,
                'oldest_eligible_at' => $ligne->oldest_eligible_at,
            ]);

        return ApiResponse::success([
            'beneficiaries' => $lignes,
            'totals' => [
                'payable_xof' => (int) $lignes->sum('payable_xof'),
                'pending_xof' => (int) $lignes->sum('pending_xof'),
                'beneficiaries_count' => $lignes->count(),
            ],
        ]);
    }

    /** Historique des versements. GET /api/v1/admin/partner-payouts */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $payouts = PartnerPayout::query()
            ->with(['beneficiary', 'creator', 'payer'])
            ->withCount('dues')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('beneficiary_id'),
                fn ($q) => $q->where('beneficiary_id', $request->integer('beneficiary_id')),
            )
            ->latest()
            ->paginate($perPage);

        return PartnerPayoutResource::collection($payouts);
    }

    /** Fiche d'un versement, avec le détail des dettes soldées. */
    public function show(PartnerPayout $payout): JsonResponse
    {
        $payout->load(['beneficiary', 'creator', 'payer', 'dues.source', 'dues.beneficiary']);

        return ApiResponse::success(['payout' => PartnerPayoutResource::make($payout)]);
    }

    /**
     * Prépare un versement à partir de dettes exigibles.
     * POST /api/v1/admin/partner-payouts
     *
     * ⚠️ **Tout est vérifié dans UNE transaction, avec verrou de ligne.** Sans
     * `lockForUpdate`, deux agents préparant un lot au même instant pour le même
     * partenaire mettraient les mêmes dettes dans deux versements : le partenaire
     * serait payé deux fois. Le verrou fait attendre le second, qui trouvera les
     * dettes déjà prises et sera refusé.
     */
    public function store(Request $request): JsonResponse
    {
        // ⚠️ Messages en français explicites. Le projet n'a pas de dossier
        // `lang/` publié et tourne en locale `en` : sans eux, Laravel renvoie
        // la CLÉ brute (« validation.required ») que l'écran afficherait telle
        // quelle. Même parti pris que `RegisterRequest`.
        $data = $request->validate([
            'due_ids' => ['required', 'array', 'min:1'],
            'due_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'due_ids.required' => 'Sélectionnez au moins un service à régler.',
            'due_ids.min' => 'Sélectionnez au moins un service à régler.',
            'note.max' => 'La note ne peut pas dépasser 1000 caractères.',
        ]);

        $payout = DB::transaction(function () use ($data, $request) {
            $dues = PartnerDue::query()
                ->whereIn('id', $data['due_ids'])
                ->lockForUpdate()
                ->get();

            if ($dues->count() !== count(array_unique($data['due_ids']))) {
                throw ValidationException::withMessages([
                    'due_ids' => ['Une des dettes sélectionnées est introuvable.'],
                ]);
            }

            // ⚠️ Un versement va à UN bénéficiaire. Mélanger deux partenaires
            // dans un lot produirait un virement dont personne ne saurait à qui
            // il a été fait — et un justificatif impossible à rattacher.
            if ($dues->pluck('beneficiary_id')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'due_ids' => ['Un versement ne peut concerner qu\'un seul bénéficiaire.'],
                ]);
            }

            foreach ($dues as $due) {
                if (! $due->status->estPayable() || $due->payout_id !== null) {
                    throw ValidationException::withMessages([
                        'due_ids' => ["La dette {$due->reference} n'est pas exigible ou figure déjà dans un versement."],
                    ]);
                }
            }

            $payout = PartnerPayout::query()->create([
                'reference' => 'PAY-'.Str::upper(Str::random(8)),
                'beneficiary_id' => $dues->first()->beneficiary_id,
                'amount_xof' => (int) $dues->sum('net_xof'),
                'status' => PartnerPayoutStatus::EN_ATTENTE->value,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Les dettes restent « exigibles » mais rattachées au lot : elles ne
            // deviennent « payées » qu'au constat du virement. Un lot préparé
            // puis abandonné ne doit pas laisser croire que l'argent est parti.
            PartnerDue::query()->whereIn('id', $dues->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            return $payout;
        });

        activity()->causedBy($request->user())->performedOn($payout)
            ->withProperties(['amount_xof' => $payout->amount_xof, 'dues' => count($data['due_ids'])])
            ->log('Versement partenaire préparé');

        return ApiResponse::created([
            'payout' => PartnerPayoutResource::make($payout->load(['beneficiary', 'dues'])),
        ]);
    }

    /**
     * Constate le virement effectué. POST /api/v1/admin/partner-payouts/{payout}/pay
     *
     * ⚠️ **Le justificatif est OBLIGATOIRE.** `owner_payouts` porte une colonne
     * `proof_path` depuis B4.4 que **rien n'a jamais écrite** — aucun endpoint ne
     * téléverse de preuve, alors que l'écran Documents du back-office compte les
     * justificatifs de reversement. On ne refait pas ici la même promesse vide :
     * sans pièce, pas de constat. C'est le seul document qui prouve, un an plus
     * tard, qu'un partenaire a bien été payé.
     */
    public function pay(Request $request, PartnerPayout $payout): JsonResponse
    {
        if ($payout->status === PartnerPayoutStatus::PAYE) {
            throw ValidationException::withMessages([
                'payout' => ['Ce versement est déjà constaté.'],
            ]);
        }

        $data = $request->validate([
            'method' => ['required', 'string', 'max:50'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            // Image ou PDF : une capture Wave ou un avis de virement scanné.
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'method.required' => 'Indiquez par quel canal le virement a été fait.',
            'proof.required' => 'Joignez le justificatif du virement : c\'est la seule preuve du paiement.',
            'proof.mimes' => 'Le justificatif doit être une image (JPG, PNG, WEBP) ou un PDF.',
            'proof.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ]);

        // Disque PRIVÉ : un justificatif de virement porte des coordonnées.
        $chemin = $request->file('proof')->store("payouts/{$payout->id}", 'local');

        $payout->update([
            'status' => PartnerPayoutStatus::PAYE->value,
            'method' => $data['method'],
            'external_reference' => $data['external_reference'] ?? null,
            'paid_at' => now(),
            'paid_by' => $request->user()->id,
            'proof_path' => $chemin,
            'proof_disk' => 'local',
            'proof_original_name' => $request->file('proof')->getClientOriginalName(),
        ]);

        // Les dettes du lot sont soldées — et seulement maintenant.
        $payout->dues()->update(['status' => PartnerDueStatus::PAYEE->value]);

        activity()->causedBy($request->user())->performedOn($payout)
            ->withProperties(['amount_xof' => $payout->amount_xof, 'method' => $data['method']])
            ->log('Versement partenaire effectué');

        return ApiResponse::success([
            'payout' => PartnerPayoutResource::make($payout->fresh()->load(['beneficiary', 'dues'])),
        ]);
    }

    /**
     * Constate l'échec du virement. POST /api/v1/admin/partner-payouts/{payout}/fail
     *
     * ⚠️ **Les dettes redeviennent libres.** Un virement mobile money peut être
     * rejeté (numéro erroné, plafond). L'argent n'est pas parti, la créance du
     * partenaire n'a pas disparu : sans cet état, l'agent n'aurait le choix
     * qu'entre laisser le lot « payé » — donc mentir — et le supprimer, ce qui
     * effacerait la tentative.
     */
    public function fail(Request $request, PartnerPayout $payout): JsonResponse
    {
        if ($payout->status === PartnerPayoutStatus::PAYE) {
            throw ValidationException::withMessages([
                'payout' => ['Un versement déjà constaté ne peut pas être déclaré en échec ; passez par un remboursement.'],
            ]);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'note.required' => 'Indiquez le motif du rejet : il explique pourquoi les services reviennent à payer.',
        ]);

        DB::transaction(function () use ($payout, $data) {
            // Détachées AVANT le changement de statut : une dette qui reste
            // rattachée à un lot échoué n'apparaîtrait plus comme payable.
            $payout->dues()->update(['payout_id' => null]);

            $payout->update([
                'status' => PartnerPayoutStatus::ECHOUE->value,
                'note' => $data['note'],
            ]);
        });

        activity()->causedBy($request->user())->performedOn($payout)
            ->withProperties(['motif' => $data['note']])
            ->log('Versement partenaire en échec');

        return ApiResponse::success([
            'payout' => PartnerPayoutResource::make($payout->fresh()->load('beneficiary')),
        ]);
    }

    /**
     * Téléchargement du justificatif, par URL SIGNÉE uniquement.
     * GET /api/v1/admin/partner-payouts/{payout}/proof
     */
    public function proof(PartnerPayout $payout): StreamedResponse
    {
        abort_if($payout->proof_path === null, 404);

        return Storage::disk($payout->proof_disk ?? 'local')
            ->download($payout->proof_path, $payout->proof_original_name ?? 'justificatif');
    }
}
