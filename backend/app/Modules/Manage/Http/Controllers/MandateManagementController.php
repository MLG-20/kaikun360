<?php

namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Enums\RentStatus;
use App\Modules\Manage\Http\Requests\StoreExpenseRequest;
use App\Modules\Manage\Http\Requests\StoreIncidentRequest;
use App\Modules\Manage\Http\Requests\StoreMandateRequest;
use App\Modules\Manage\Http\Requests\StorePayoutRequest;
use App\Modules\Manage\Http\Requests\StoreRentRequest;
use App\Modules\Manage\Http\Resources\ExpenseResource;
use App\Modules\Manage\Http\Resources\IncidentResource;
use App\Modules\Manage\Http\Resources\MandateResource;
use App\Modules\Manage\Http\Resources\OwnerPayoutResource;
use App\Modules\Manage\Http\Resources\RentResource;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use App\Modules\Immo\Models\Property;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints de gestion locative réservés aux AGENTS (phase B4.6).
 *
 * Toutes ces routes exigent la permission `gerer:gestion-locative` (agent, admin,
 * super_admin) appliquée par le middleware `can:` ; le propriétaire, lui, n'a
 * qu'un accès en lecture via ManageController. On crée et fait évoluer ici les
 * mandats, loyers, incidents, dépenses et reversements.
 */
class MandateManagementController extends Controller
{
    /**
     * Crée un mandat de gestion. POST /api/v1/manage/mandates
     *
     * L'`owner_id` est déduit du propriétaire du bien (cohérence des données).
     */
    public function storeMandate(StoreMandateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $property = Property::findOrFail($data['property_id']);

        // F8.15.d — UN SEUL MANDAT VIVANT PAR BIEN. Rien ne l'empêchait tant
        // qu'aucun écran ne créait de mandat : tous venaient du seeder. Deux
        // mandats sur le même bien, ce sont deux commissions sur le même loyer,
        // deux reversements au même propriétaire et un rapport mensuel faux —
        // et rien à l'écran pour comprendre d'où vient l'écart. Un mandat
        // TERMINÉ, lui, ne bloque pas : c'est le cas normal du renouvellement.
        $vivant = ManagementMandate::where('property_id', $property->id)
            ->whereIn('status', [
                MandateStatus::EN_ATTENTE->value,
                MandateStatus::ACTIF->value,
                MandateStatus::SUSPENDU->value,
            ])
            ->first();

        if ($vivant !== null) {
            throw ValidationException::withMessages([
                'property_id' => [
                    "Ce bien est déjà sous mandat ({$vivant->reference}, {$vivant->status->label()}). "
                    .'Terminez le mandat en cours avant d’en ouvrir un nouveau.',
                ],
            ]);
        }

        $mandate = ManagementMandate::create([
            'reference' => 'MND-'.Str::upper(Str::random(8)),
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'commission_rate' => $data['commission_rate'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? MandateStatus::EN_ATTENTE->value,
            'terms' => $data['terms'] ?? null,
        ]);

        return ApiResponse::created(['mandate' => MandateResource::make($mandate)]);
    }

    /**
     * Ajoute une échéance de loyer à un mandat. POST .../mandates/{mandate}/rents
     */
    public function storeRent(StoreRentRequest $request, ManagementMandate $mandate): JsonResponse
    {
        $rent = $mandate->rents()->create($request->validated() + [
            'status' => RentStatus::IMPAYE->value,
        ]);

        return ApiResponse::created(['rent' => RentResource::make($rent)]);
    }

    /**
     * Marque un loyer comme payé. PATCH /api/v1/manage/rents/{rent}/pay
     */
    public function markRentPaid(Rent $rent): JsonResponse
    {
        $rent->update([
            'status' => RentStatus::PAYE->value,
            'paid_at' => now(),
        ]);

        return ApiResponse::success(['rent' => RentResource::make($rent)]);
    }

    /**
     * Signale un incident sur le bien d'un mandat. POST .../mandates/{mandate}/incidents
     */
    public function storeIncident(StoreIncidentRequest $request, ManagementMandate $mandate): JsonResponse
    {
        $incident = Incident::create($request->validated() + [
            'reference' => 'INC-'.Str::upper(Str::random(8)),
            'property_id' => $mandate->property_id,
            'reported_by' => $request->user()->id,
            'status' => IncidentStatus::OUVERT->value,
        ]);

        return ApiResponse::created(['incident' => IncidentResource::make($incident)]);
    }

    /**
     * Marque un incident comme résolu. PATCH /api/v1/manage/incidents/{incident}/resolve
     */
    public function resolveIncident(Incident $incident): JsonResponse
    {
        $incident->update([
            'status' => IncidentStatus::RESOLU->value,
            'resolved_at' => now(),
        ]);

        return ApiResponse::success(['incident' => IncidentResource::make($incident)]);
    }

    /**
     * Enregistre une dépense sur le bien d'un mandat. POST .../mandates/{mandate}/expenses
     */
    public function storeExpense(StoreExpenseRequest $request, ManagementMandate $mandate): JsonResponse
    {
        $data = $request->validated();

        // Si un incident est rattaché, il doit concerner le bien du mandat.
        if (! empty($data['incident_id'])) {
            $belongs = Incident::whereKey($data['incident_id'])
                ->where('property_id', $mandate->property_id)->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'incident_id' => "L'incident ne concerne pas le bien de ce mandat.",
                ]);
            }
        }

        $expense = Expense::create($data + ['property_id' => $mandate->property_id]);

        return ApiResponse::created(['expense' => ExpenseResource::make($expense)]);
    }

    /**
     * Crée un reversement (en attente) pour le propriétaire. POST .../mandates/{mandate}/payouts
     */
    public function storePayout(StorePayoutRequest $request, ManagementMandate $mandate): JsonResponse
    {
        $payout = $mandate->payouts()->create($request->validated() + [
            'reference' => 'RVS-'.Str::upper(Str::random(8)),
            'owner_id' => $mandate->owner_id,
            'status' => OwnerPayoutStatus::EN_ATTENTE->value,
        ]);

        return ApiResponse::created(['payout' => OwnerPayoutResource::make($payout)]);
    }

    /**
     * Constate le reversement effectué. POST /api/v1/manage/payouts/{payout}/pay
     *
     * ⚠️ **Le justificatif est désormais OBLIGATOIRE** (2026-08-06). La colonne
     * `proof_path` existait depuis B4.4 et **rien ne l'écrivait** : ce geste
     * posait le statut et la date, sans jamais demander de preuve. L'écran
     * Documents du back-office comptait pourtant les justificatifs de
     * reversement — il comptait donc toujours zéro.
     *
     * Aligné sur `partner_payouts` (F8.16.a), qui exige la pièce : imposer la
     * preuve d'un côté et la rendre impossible de l'autre n'aurait aucun sens,
     * alors que c'est le même acte — sortir de l'argent vers un partenaire.
     *
     * ⚠️ **La méthode HTTP passe de PATCH à POST** : un téléversement de fichier
     * voyage en `multipart/form-data`, que PHP ne sait décoder que sur un POST
     * (`$_FILES` reste vide sur un PATCH). Le geste est de toute façon un
     * enregistrement d'événement, pas une correction de champ.
     */
    public function markPayoutPaid(Request $request, OwnerPayout $payout): JsonResponse
    {
        if ($payout->status === OwnerPayoutStatus::EFFECTUE) {
            throw ValidationException::withMessages([
                'payout' => ['Ce reversement est déjà constaté.'],
            ]);
        }

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'proof.required' => "Joignez le justificatif du virement : c'est la seule preuve du paiement.",
            'proof.mimes' => 'Le justificatif doit être une image (JPG, PNG, WEBP) ou un PDF.',
            'proof.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ]);

        // Disque PRIVÉ : une preuve de virement porte des coordonnées.
        $fichier = $request->file('proof');

        $payout->update([
            'status' => OwnerPayoutStatus::EFFECTUE->value,
            'paid_at' => now(),
            'paid_by' => $request->user()->id,
            'proof_path' => $fichier->store("payouts/owners/{$payout->id}", 'local'),
            'proof_disk' => 'local',
            'proof_original_name' => $fichier->getClientOriginalName(),
        ]);

        // Action financière sensible : on l'audite (cf. B15).
        activity()->causedBy($request->user())->performedOn($payout)
            ->withProperties(['amount_xof' => $payout->amount_xof])
            ->log('Reversement propriétaire effectué');

        return ApiResponse::success(['payout' => OwnerPayoutResource::make($payout->fresh())]);
    }

    /**
     * Téléchargement du justificatif, par URL SIGNÉE uniquement.
     * GET /api/v1/manage/payouts/{payout}/proof
     */
    public function downloadPayoutProof(OwnerPayout $payout): StreamedResponse
    {
        abort_if($payout->proof_path === null, 404);

        return Storage::disk($payout->proof_disk ?? 'local')
            ->download($payout->proof_path, $payout->proof_original_name ?? 'justificatif');
    }
}
