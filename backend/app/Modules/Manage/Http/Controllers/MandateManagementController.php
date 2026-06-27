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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * Marque un reversement comme effectué. PATCH /api/v1/manage/payouts/{payout}/pay
     */
    public function markPayoutPaid(Request $request, OwnerPayout $payout): JsonResponse
    {
        $payout->update([
            'status' => OwnerPayoutStatus::EFFECTUE->value,
            'paid_at' => now(),
        ]);

        // Action financière sensible : on l'audite (cf. B15).
        activity()->causedBy($request->user())->performedOn($payout)->log('Reversement propriétaire effectué');

        return ApiResponse::success(['payout' => OwnerPayoutResource::make($payout)]);
    }
}
